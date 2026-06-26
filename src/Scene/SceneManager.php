<?php
declare(strict_types=1);

/*
 * This file is part of Laragram.
 *
 * (c) Sergey Lapin <me@wekser.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Wekser\Laragram\Scene;

use Closure;
use Illuminate\Support\Facades\Validator;
use Wekser\Laragram\BotRequest;
use Wekser\Laragram\Http\RequestTransformer;
use Wekser\Laragram\Http\ResponseTransformer;
use Wekser\Laragram\Routing\RouteCollection;
use Wekser\Laragram\Routing\Router;
use Wekser\Laragram\Support\UpdateType;

/**
 * Runtime for scenes (multi-step wizards). Produces the same output array shape
 * as Routing\Router so the rest of the pipeline (CallbackFormed → LogSession →
 * ResponseDispatcher) is unchanged.
 *
 * While a user is inside a scene their station is the sentinel "@scene:<name>";
 * the current step and collected answers live in laragram_sessions.payload under
 * output['scene'], persisted by LogSession.
 */
class SceneManager
{
    /** Station prefix marking a user as being inside a scene. */
    public const PREFIX = '@scene:';

    public function __construct(private readonly SceneRegistry $registry) {}

    // -------------------------------------------------------------------------
    // Definition / entry API (used via the BotScene facade)
    // -------------------------------------------------------------------------

    /**
     * Define a scene (delegates to the registry). Used inside the scenes file.
     */
    public function define(string $name): Scene
    {
        return $this->registry->define($name);
    }

    /**
     * Begin a scene from a route handler: return value of BotScene::enter().
     *
     * Scenes require the database auth driver to persist step state across
     * updates; under any other driver the station never leaves 'start', so we
     * fail fast rather than silently dropping the wizard after the first step.
     *
     * @param array<string, mixed> $data Pre-seeded answers.
     */
    public function enter(string $name, array $data = []): SceneTransition
    {
        $driver = config('laragram.auth.driver');

        if ($driver !== 'database') {
            throw new \RuntimeException(
                "Laragram scenes require the 'database' auth driver to persist multi-step " .
                "state; the current driver is '{$driver}'."
            );
        }

        return new SceneTransition($name, $data);
    }

    /**
     * Whether a station string marks an active scene. Static so the dispatch
     * fork in Laragram::run() can ask without resolving the manager.
     */
    public static function isSceneStation(string $station): bool
    {
        return str_starts_with($station, self::PREFIX);
    }

    // -------------------------------------------------------------------------
    // Pipeline entry points (called by Router::prepareResponse / Laragram::run)
    // -------------------------------------------------------------------------

    /**
     * Start a scene: render the first step's question and record the initial
     * scene state. $request is the BotRequest already built for the route that
     * triggered the entry.
     *
     * @return array<string, mixed>|null
     */
    public function start(BotRequest $request, SceneTransition $transition): ?array
    {
        $scene = $this->registry->get($transition->name);

        if ($scene === null) {
            app('log')->warning('laragram: cannot start unknown scene', [
                'scene' => $transition->name,
            ]);

            return null;
        }

        $context = new SceneContext($request, $transition->initialData);
        $first   = $this->firstEligibleStep($scene, $context);

        // No step is eligible (all skipped by when()): finish immediately.
        if ($first === null) {
            return $this->complete($request, $scene, $context);
        }

        $output  = $this->buildOutput($request, $this->renderPrompt($first, $context));
        $station = self::PREFIX . $scene->name();

        $output['response']['redirect'] = $station;
        $output['scene'] = $this->sceneState($scene, $first->key(), $transition->initialData);

        return $output;
    }

    /**
     * Continue a scene: validate the answer to the current step and either
     * re-ask, advance, or complete.
     *
     * @param array<string, mixed> $update     Raw Telegram update.
     * @param string               $station    Current "@scene:<name>" station.
     * @param array<string, mixed>|null $state  Scene state from session payload.
     * @return array<string, mixed>|null
     */
    public function continue(array $update, string $station, ?array $state): ?array
    {
        $name    = $state['name'] ?? substr($station, strlen(self::PREFIX));
        $scene   = $this->registry->get((string) $name);
        $request = $this->buildRequest($update, $station);
        $input   = $request->query();

        // A global command escapes any scene and is handled by the normal router.
        if ($this->isGlobalCommand($input)) {
            return $this->escape($update);
        }

        // Scene removed/renamed mid-flow, or corrupt state: drop out cleanly.
        if ($scene === null || ($step = $scene->stepAt((string) ($state['step'] ?? ''))) === null) {
            return $this->reset($request, null, null);
        }

        $data    = (array) ($state['data'] ?? []);
        $context = new SceneContext($request, $data);

        // Inactivity timeout — expire the scene.
        if ($this->isTimedOut($scene, $state)) {
            return $this->reset($request, $scene->timeoutHandler(), $context);
        }

        // Cancel command — abort the scene.
        if (is_string($input) && in_array($input, $scene->cancelCommands(), true)) {
            return $this->reset($request, $scene->cancelHandler(), $context);
        }

        // Back command — return to the previous eligible step (or re-ask the first).
        if ($scene->backEnabled() && is_string($input) && in_array($input, $scene->backCommands(), true)) {
            $target = $this->prevEligibleStep($scene, $step->key(), $context) ?? $step;

            return $this->askStep($request, $station, $scene, $target, $data, $context);
        }

        $value = $this->extractAnswer($step, $request);

        if (!$this->passesValidation($step, $value)) {
            // Invalid input: show onInvalid() if set, else re-ask; state unchanged.
            $prompt = $step->invalidPrompt() ?? $step->prompt();
            $output = $this->buildOutput($request, $this->renderPromptValue($prompt, $context));

            $output['response']['redirect'] = $station;
            // Preserve the original timestamp: a re-ask is not progress, so it
            // must not refresh the inactivity timer (otherwise a stream of
            // invalid answers would keep a scene alive indefinitely).
            $output['scene'] = $this->sceneState(
                $scene,
                $step->key(),
                $data,
                isset($state['at']) ? (int) $state['at'] : null,
            );

            return $output;
        }

        // Store the (optionally transformed) answer.
        $data[$step->key()] = $this->applyTransform($step, $value, $context);
        $context = new SceneContext($request, $data);

        // Changing an earlier answer (via back navigation) can make a later
        // conditional step ineligible; drop any answers it had already collected
        // so onComplete never sees data inconsistent with the new answers.
        $data    = $this->pruneIneligibleAnswers($scene, $data, $context);
        $context = new SceneContext($request, $data);

        $next = $this->nextEligibleStep($scene, $step->key(), $context);

        if ($next !== null) {
            return $this->askStep($request, $station, $scene, $next, $data, $context);
        }

        // Last step answered — run the completion handler and leave the scene.
        return $this->complete($request, $scene, $context);
    }

    // -------------------------------------------------------------------------
    // Transitions
    // -------------------------------------------------------------------------

    /**
     * Ask a step's question and record the scene state pointing at it, staying
     * inside the scene (station unchanged). Shared by advancing to the next step
     * and by back navigation re-asking an earlier one.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function askStep(
        BotRequest $request,
        string     $station,
        Scene      $scene,
        Step       $step,
        array      $data,
        SceneContext $context,
    ): array {
        $output = $this->buildOutput($request, $this->renderPrompt($step, $context));

        $output['response']['redirect'] = $station; // stay in the scene
        $output['scene'] = $this->sceneState($scene, $step->key(), $data);

        return $output;
    }

    /**
     * Run the completion handler, deliver its response(s), and exit the scene.
     *
     * @return array<string, mixed>
     */
    private function complete(BotRequest $request, Scene $scene, SceneContext $context): array
    {
        $responses = $this->runHandler($scene->completeHandler(), $context);

        return $this->finalizeExit($this->buildOutput($request, $responses));
    }

    /**
     * Exit the scene, optionally running a handler (e.g. onCancel). Also used to
     * recover from an unknown scene / corrupt state.
     *
     * @return array<string, mixed>
     */
    private function reset(BotRequest $request, array|Closure|null $handler, ?SceneContext $context): array
    {
        $responses = $context !== null ? $this->runHandler($handler, $context) : null;

        return $this->finalizeExit($this->buildOutput($request, $responses));
    }

    /**
     * Clear the scene state and ensure the user lands on a real station: the
     * exit handler's redirect wins, but if it gave none — or pointed back at a
     * "@scene:" station — fall back to 'start' so the user is never stranded.
     *
     * @param array<string, mixed> $output
     * @return array<string, mixed>
     */
    private function finalizeExit(array $output): array
    {
        $redirect = $output['response']['redirect'] ?? null;

        if ($redirect === null || self::isSceneStation((string) $redirect)) {
            $redirect = 'start';
        }

        $output['response']['redirect'] = $redirect;
        $output['scene'] = null;

        return $output;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Build the BotRequest for the current update using a synthetic route that
     * describes the scene step (no controller — the listener is the type default).
     */
    private function buildRequest(array $update, string $station): BotRequest
    {
        $type     = UpdateType::detect($update);
        $listener = RouteCollection::DEFAULT_LISTENERS[$type] ?? 'text';

        $route = [
            'event'    => $type,
            'listener' => $listener,
            'contains' => null,
        ];

        return (new RequestTransformer($type, $update))->build($route, $station);
    }

    /**
     * Render a step's prompt into a controller-style response (BotResponse,
     * string, array, or null).
     */
    private function renderPrompt(Step $step, SceneContext $context): mixed
    {
        return $this->renderPromptValue($step->prompt(), $context);
    }

    /**
     * Render a prompt value (view name, closure, or null) into a controller-style
     * response. Shared by ask() prompts and onInvalid() prompts.
     */
    private function renderPromptValue(string|Closure|null $prompt, SceneContext $context): mixed
    {
        if ($prompt === null) {
            return null;
        }

        if ($prompt instanceof Closure) {
            return $prompt($context);
        }

        // String prompt = a view name; collected answers are exposed to the view.
        return app('laragram.response')->view($prompt, $context->all());
    }

    /**
     * Normalize controller-style response(s) into the output array. When the
     * response is empty, return a base output with no views (the caller sets the
     * station / scene state), so the session is still persisted.
     *
     * @return array<string, mixed>
     */
    private function buildOutput(BotRequest $request, mixed $responses): array
    {
        $output = (new ResponseTransformer())->getResponse($request, $responses);

        if ($output === null) {
            $output = $request->getRequest();
            $output['response'] = ['views' => [], 'redirect' => null];
        }

        return $output;
    }

    /**
     * Extract the raw answer for a step. Defaults to the type-appropriate field
     * (message text, callback data, …) via BotRequest::query().
     */
    private function extractAnswer(Step $step, BotRequest $request): mixed
    {
        $extractor = $step->extractor();

        return $extractor !== null ? $extractor($request) : $request->query();
    }

    private function passesValidation(Step $step, mixed $value): bool
    {
        $rules = $step->validationRules();

        if (empty($rules)) {
            return true;
        }

        return Validator::make(
            [$step->key() => $value],
            [$step->key() => $rules],
            $step->validationMessages(),
        )->passes();
    }

    private function applyTransform(Step $step, mixed $value, SceneContext $context): mixed
    {
        $transform = $step->transformer();

        return $transform !== null ? $transform($value, $context) : $value;
    }

    /**
     * Build a scene-state payload, stamping the current time for timeout checks.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function sceneState(Scene $scene, string $step, array $data, ?int $at = null): array
    {
        return [
            'name' => $scene->name(),
            'step' => $step,
            'data' => $data,
            'at'   => $at ?? time(),
        ];
    }

    private function isEligible(Step $step, SceneContext $context): bool
    {
        $when = $step->condition();

        return $when === null || (bool) $when($context);
    }

    /**
     * Remove answers belonging to steps that are no longer eligible under the
     * current set of answers. Only step-keyed entries are touched, so pre-seeded
     * data not tied to a step is preserved.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function pruneIneligibleAnswers(Scene $scene, array $data, SceneContext $context): array
    {
        foreach ($scene->steps() as $key => $step) {
            if (array_key_exists($key, $data) && !$this->isEligible($step, $context)) {
                unset($data[$key]);
            }
        }

        return $data;
    }

    /**
     * First step whose when() condition passes, given the current answers.
     */
    private function firstEligibleStep(Scene $scene, SceneContext $context): ?Step
    {
        foreach ($scene->steps() as $step) {
            if ($this->isEligible($step, $context)) {
                return $step;
            }
        }

        return null;
    }

    /**
     * Next eligible step after $key.
     */
    private function nextEligibleStep(Scene $scene, string $key, SceneContext $context): ?Step
    {
        $keys  = array_keys($scene->steps());
        $index = array_search($key, $keys, true);

        if ($index === false) {
            return null;
        }

        for ($i = $index + 1; $i < count($keys); $i++) {
            $step = $scene->stepAt($keys[$i]);

            if ($step !== null && $this->isEligible($step, $context)) {
                return $step;
            }
        }

        return null;
    }

    /**
     * Previous eligible step before $key.
     */
    private function prevEligibleStep(Scene $scene, string $key, SceneContext $context): ?Step
    {
        $keys  = array_keys($scene->steps());
        $index = array_search($key, $keys, true);

        if ($index === false) {
            return null;
        }

        for ($i = $index - 1; $i >= 0; $i--) {
            $step = $scene->stepAt($keys[$i]);

            if ($step !== null && $this->isEligible($step, $context)) {
                return $step;
            }
        }

        return null;
    }

    /**
     * Whether the input is a configured global command that escapes any scene.
     */
    private function isGlobalCommand(mixed $input): bool
    {
        if (!is_string($input)) {
            return false;
        }

        return in_array($input, (array) config('laragram.scenes.global_commands', []), true);
    }

    /**
     * Leave the scene and hand the update to the normal router, so a global
     * command (e.g. /start) is processed as if the user were not in a scene.
     *
     * @return array<string, mixed>
     */
    private function escape(array $update): array
    {
        $output = (new Router('start'))->dispatch($update);

        if ($output === null) {
            $output = $this->buildOutput($this->buildRequest($update, 'start'), null);
            $output['response']['redirect'] = 'start';
        }

        // Clear the old scene — unless the escaped-to handler itself started a
        // new one (returned BotScene::enter()), in which case Router::start()
        // already populated output['scene'] and we must keep it.
        $output['scene'] = $output['scene'] ?? null;

        return $output;
    }

    /**
     * Whether the scene has been inactive longer than its configured timeout.
     *
     * @param array<string, mixed>|null $state
     */
    private function isTimedOut(Scene $scene, ?array $state): bool
    {
        $ttl = $scene->timeoutMinutes();
        $at  = $state['at'] ?? null;

        if ($ttl === null || $at === null) {
            return false;
        }

        return (time() - (int) $at) > $ttl * 60;
    }

    /**
     * Run a [Controller, method] or Closure handler with the scene context.
     */
    private function runHandler(array|Closure|null $handler, SceneContext $context): mixed
    {
        if ($handler === null) {
            return null;
        }

        if ($handler instanceof Closure) {
            return $handler($context);
        }

        [$class, $method] = $handler;

        return app($class)->{$method}($context);
    }
}
