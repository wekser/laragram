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

namespace Wekser\Laragram;

use Illuminate\Http\Request;
use Wekser\Laragram\Events\CallbackFormed;
use Wekser\Laragram\Exceptions\ExceptionHandler;
use Wekser\Laragram\Facades\BotAuth;
use Wekser\Laragram\Http\ResponseDispatcher;
use Wekser\Laragram\Jobs\ProcessTelegramUpdate;
use Wekser\Laragram\Models\User;
use Wekser\Laragram\Routing\Router;
use Wekser\Laragram\Scene\SceneManager;

class Laragram
{
    /**
     * The current user station.
     *
     * @var string|null
     */
    protected ?string $station;

    /**
     * The request from to Telegram.
     *
     * @var \Illuminate\Http\Request
     */
    protected Request $request;

    /**
     * The output data.
     *
     * @var array|null
     */
    protected ?array $output;

    /**
     * The current authorized user.
     *
     * @var User|null
     */
    protected ?User $user = null;

    /**
     * Scene state restored from the current session payload (null when not in a scene).
     *
     * @var array|null
     */
    protected ?array $sceneState = null;

    /**
     * The webhook entry point.
     *
     * When queueing is enabled the raw update is pushed onto a queue and the
     * webhook returns 'OK' 200 immediately — the router and the outbound Bot API
     * calls then run inside a queue worker (see ProcessTelegramUpdate). When it
     * is disabled the update is processed synchronously, exactly as before.
     *
     * The verify/auth/hook/throttle middleware have already run on the HTTP
     * request before this method, so what gets queued is always a verified,
     * non-bot, non-duplicate, rate-limited update.
     *
     * @param \Illuminate\Http\Request $request
     * @return mixed
     */
    public function index(Request $request): mixed
    {
        if ($this->shouldQueue()) {
            ProcessTelegramUpdate::dispatch($request->all())
                ->onConnection(config('laragram.queue.connection'))
                ->onQueue(config('laragram.queue.queue') ?? 'default');

            return $this->back();
        }

        return $this->handle($request);
    }

    /**
     * Run the full processing pipeline synchronously.
     *
     * Shared by the webhook entry point (queueing disabled) and the queued
     * ProcessTelegramUpdate job, so message handling behaves identically in
     * either mode. Returns the 'OK' 200 webhook response; the job ignores it.
     *
     * @param \Illuminate\Http\Request $request
     * @return mixed
     */
    public function handle(Request $request): mixed
    {
        $this->request = $request;
        $this->user    = BotAuth::user();

        $this->bootstrap();

        $this->run();

        $this->fireEvent();

        $this->deliver();

        return $this->back();
    }

    /**
     * Whether incoming updates should be deferred to a queue worker.
     *
     * @return bool
     */
    protected function shouldQueue(): bool
    {
        return (bool) config('laragram.queue.enabled', false);
    }

    /**
     * Create the Laragram.
     *
     * @return void
     */
    protected function bootstrap(): void
    {
        $locale = $this->user?->settings?->get('language', config('app.locale'));
        app('translator')->setLocale($locale ?? config('app.locale') ?? 'en');

        $this->station = (config('laragram.auth.driver') !== 'database') ? 'start' : $this->defineStation();
    }

    /**
     * Get the user's current station and restore any active scene state.
     *
     * @return string
     */
    protected function defineStation(): string
    {
        $session = $this->user?->session();

        if (empty($session)) {
            return 'start';
        }

        $this->sceneState = $session->payload['scene'] ?? null;

        return $session->station;
    }

    /**
     * Run the Laragram.
     *
     * @return void|null
     */
    protected function run()
    {
        try {
            $this->output = SceneManager::isSceneStation($this->station)
                ? app(SceneManager::class)->continue($this->request->all(), $this->station, $this->sceneState)
                : (new Router($this->station))->dispatch($this->request->all());
        } catch (\Throwable $exception) {
            ExceptionHandler::handle($exception);
        }
    }

    /**
     * To trigger an event on the Laragram response.
     *
     * @return void
     */
    protected function fireEvent(): void
    {
        if (!empty($this->output)) {
            event(new CallbackFormed($this->user, $this->output));
        }
    }

    /**
     * Deliver the formed responses to Telegram as outbound Bot API calls.
     *
     * Runs after the session is persisted (fireEvent), so the user's next station
     * is recorded even if a message fails to deliver. Sends nothing when no route
     * matched or the controller returned no response.
     *
     * @return void
     */
    protected function deliver(): void
    {
        if (empty($this->output)) {
            return;
        }

        app(ResponseDispatcher::class)->send($this->output['response']['views'] ?? []);
    }

    /**
     * The Laragram back response.
     *
     * Messages are delivered via outbound Bot API calls in deliver(), so the
     * webhook body itself is always an empty 'OK' 200 — Telegram only needs to
     * know the update was received.
     *
     * @return mixed
     */
    protected function back(): mixed
    {
        return response('OK', 200);
    }
}
