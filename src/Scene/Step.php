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
use Wekser\Laragram\Support\Media;

/**
 * One step of a scene: the question to ask, how to validate the answer, and an
 * optional transform to apply before storing it.
 *
 * Step-level methods (ask/rules/messages/transform/using) return the Step for
 * chaining; scene-level methods (step/onComplete/onCancel/cancelOn) delegate
 * back to the parent Scene so a single fluent chain can describe a whole scene.
 */
class Step
{
    /** View name (string) or a Closure(SceneContext): BotResponse|string|array. */
    private string|Closure|null $prompt = null;

    /** Optional prompt shown when validation fails (defaults to re-asking $prompt). */
    private string|Closure|null $invalidPrompt = null;

    /** @var array<int|string, mixed> Laravel validation rules for the answer. */
    private array $rules = [];

    /** @var array<string, string> Custom validation messages. */
    private array $messages = [];

    /** Optional transform applied to the raw answer before it is stored. */
    private ?Closure $transform = null;

    /** Optional custom extractor: Closure(BotRequest): mixed. */
    private ?Closure $extractor = null;

    /** Optional condition: Closure(SceneContext): bool — step is skipped when false. */
    private ?Closure $condition = null;

    public function __construct(
        private readonly Scene  $scene,
        private readonly string $key,
    ) {}

    // -------------------------------------------------------------------------
    // Step-level fluent API
    // -------------------------------------------------------------------------

    /**
     * The question to send when this step is reached.
     *
     * @param string|Closure $prompt A view name, or a Closure(SceneContext) returning
     *                               a BotResponse, a string, or an array of them.
     */
    public function ask(string|Closure $prompt): static
    {
        $this->prompt = $prompt;

        return $this;
    }

    /**
     * Laravel validation rules applied to the answer. On failure the same
     * question is asked again (or onInvalid(), if set) and the user stays on
     * this step.
     *
     * @param array<int|string, mixed> $rules
     */
    public function rules(array $rules): static
    {
        $this->rules = $rules;

        return $this;
    }

    /**
     * Message shown when validation fails, instead of re-asking the question.
     *
     * @param string|Closure $prompt A view name, or a Closure(SceneContext) returning
     *                               a BotResponse, a string, or an array of them.
     */
    public function onInvalid(string|Closure $prompt): static
    {
        $this->invalidPrompt = $prompt;

        return $this;
    }

    /**
     * Custom validation messages, keyed as in Laravel (e.g. "size.in").
     *
     * @param array<string, string> $messages
     */
    public function messages(array $messages): static
    {
        $this->messages = $messages;

        return $this;
    }

    /**
     * Transform the raw answer before storing it.
     *
     * @param Closure $transform Closure(mixed $value, SceneContext $ctx): mixed
     */
    public function transform(Closure $transform): static
    {
        $this->transform = $transform;

        return $this;
    }

    /**
     * Override how the answer is read from the update. By default the
     * type-appropriate field is used (message text, callback data, …).
     *
     * @param Closure $extractor Closure(BotRequest $request): mixed
     */
    public function using(Closure $extractor): static
    {
        $this->extractor = $extractor;

        return $this;
    }

    /**
     * Only run this step when the condition holds; otherwise it is skipped
     * (both when entering and when navigating). The closure receives the
     * SceneContext with the answers collected so far.
     *
     * @param Closure $condition Closure(SceneContext $ctx): bool
     */
    public function when(Closure $condition): static
    {
        $this->condition = $condition;

        return $this;
    }

    /**
     * Read the answer from the message text (explicit; this is also the default).
     */
    public function expectText(): static
    {
        return $this->using(static fn ($request) => $request->get('text'));
    }

    /**
     * Read the answer from a callback_query's data.
     */
    public function expectCallback(): static
    {
        return $this->using(static fn ($request) => $request->get('data'));
    }

    /**
     * Read a shared contact (the raw contact object: phone_number, first_name, …).
     */
    public function expectContact(): static
    {
        return $this->using(static fn ($request) => $request->get('contact'));
    }

    /**
     * Read a shared location (the raw location object: latitude, longitude).
     */
    public function expectLocation(): static
    {
        return $this->using(static fn ($request) => $request->get('location'));
    }

    /**
     * Read the file_id of the largest size of an uploaded photo.
     */
    public function expectPhoto(): static
    {
        return $this->using(static fn ($request) => Media::largestPhotoFileId($request->get('photo')));
    }

    // -------------------------------------------------------------------------
    // Scene-level delegation (lets the chain flow back to the parent Scene)
    // -------------------------------------------------------------------------

    public function step(string $key): Step
    {
        return $this->scene->step($key);
    }

    public function onComplete(array|Closure $handler): Scene
    {
        return $this->scene->onComplete($handler);
    }

    public function onCancel(array|Closure $handler): Scene
    {
        return $this->scene->onCancel($handler);
    }

    public function cancelOn(string|array $commands): Scene
    {
        return $this->scene->cancelOn($commands);
    }

    public function allowBack(string|array $commands = '/back'): Scene
    {
        return $this->scene->allowBack($commands);
    }

    public function timeout(int $minutes): Scene
    {
        return $this->scene->timeout($minutes);
    }

    public function onTimeout(array|Closure $handler): Scene
    {
        return $this->scene->onTimeout($handler);
    }

    // -------------------------------------------------------------------------
    // Accessors
    // -------------------------------------------------------------------------

    public function key(): string
    {
        return $this->key;
    }

    public function prompt(): string|Closure|null
    {
        return $this->prompt;
    }

    public function invalidPrompt(): string|Closure|null
    {
        return $this->invalidPrompt;
    }

    /** @return array<int|string, mixed> */
    public function validationRules(): array
    {
        return $this->rules;
    }

    /** @return array<string, string> */
    public function validationMessages(): array
    {
        return $this->messages;
    }

    public function transformer(): ?Closure
    {
        return $this->transform;
    }

    public function extractor(): ?Closure
    {
        return $this->extractor;
    }

    public function condition(): ?Closure
    {
        return $this->condition;
    }
}
