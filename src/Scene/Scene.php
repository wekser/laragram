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

/**
 * Definition of a multi-step conversation (wizard).
 *
 *   BotScene::define('order')
 *       ->step('size')->ask('order.size')->rules(['required', 'in:S,M,L'])
 *       ->step('address')->ask(fn ($ctx) => BotResponse::text('Address?'))->rules(['min:5'])
 *       ->cancelOn('/cancel')->onCancel([OrderController::class, 'cancelled'])
 *       ->onComplete([OrderController::class, 'place']);
 *
 * The fluent chain mixes step-level methods (on Step) and scene-level methods
 * (here) — Step delegates the scene-level ones back to this object.
 */
class Scene
{
    /** @var array<string, Step> Ordered steps keyed by step name. */
    private array $steps = [];

    /** Called with a SceneContext after the last step; returns BotResponse(s). */
    private array|Closure|null $onComplete = null;

    /** Called with a SceneContext when the user cancels; returns BotResponse(s). */
    private array|Closure|null $onCancel = null;

    /** @var string[]|null Commands that abort the scene; null = use config default. */
    private ?array $cancelCommands = null;

    /** @var string[]|null Commands that step back; null = back navigation disabled. */
    private ?array $backCommands = null;

    /** Minutes of inactivity after which the scene expires; null = no scene-level timeout. */
    private ?int $ttl = null;

    /** Called with a SceneContext when the scene times out; returns BotResponse(s). */
    private array|Closure|null $onTimeout = null;

    public function __construct(private readonly string $name) {}

    // -------------------------------------------------------------------------
    // Fluent builder
    // -------------------------------------------------------------------------

    /**
     * Add a step. Returns the Step so its question/validation can be chained.
     */
    public function step(string $key): Step
    {
        return $this->steps[$key] = new Step($this, $key);
    }

    /**
     * Handler invoked once every step has a valid answer.
     *
     * @param array|Closure $handler [Controller::class, 'method'] or Closure(SceneContext).
     */
    public function onComplete(array|Closure $handler): static
    {
        $this->onComplete = $handler;

        return $this;
    }

    /**
     * Handler invoked when the user sends a cancel command.
     *
     * @param array|Closure $handler [Controller::class, 'method'] or Closure(SceneContext).
     */
    public function onCancel(array|Closure $handler): static
    {
        $this->onCancel = $handler;

        return $this;
    }

    /**
     * Commands that abort the scene at any step (default: config laragram.scenes.cancel_commands).
     *
     * @param string|string[] $commands
     */
    public function cancelOn(string|array $commands): static
    {
        $this->cancelCommands = array_values((array) $commands);

        return $this;
    }

    /**
     * Enable back navigation: the given command(s) return the user to the
     * previous (eligible) step. Disabled unless called.
     *
     * @param string|string[] $commands
     */
    public function allowBack(string|array $commands = '/back'): static
    {
        $this->backCommands = array_values((array) $commands);

        return $this;
    }

    /**
     * Expire the scene after $minutes of inactivity (measured from the last step).
     */
    public function timeout(int $minutes): static
    {
        $this->ttl = $minutes;

        return $this;
    }

    /**
     * Handler invoked when the scene expires (see timeout()).
     *
     * @param array|Closure $handler [Controller::class, 'method'] or Closure(SceneContext).
     */
    public function onTimeout(array|Closure $handler): static
    {
        $this->onTimeout = $handler;

        return $this;
    }

    // -------------------------------------------------------------------------
    // Accessors / navigation
    // -------------------------------------------------------------------------

    public function name(): string
    {
        return $this->name;
    }

    /** @return array<string, Step> */
    public function steps(): array
    {
        return $this->steps;
    }

    public function firstStep(): ?Step
    {
        foreach ($this->steps as $step) {
            return $step;
        }

        return null;
    }

    public function stepAt(string $key): ?Step
    {
        return $this->steps[$key] ?? null;
    }

    /**
     * The step following $key, or null if $key is the last (or unknown) step.
     */
    public function nextStep(string $key): ?Step
    {
        $keys  = array_keys($this->steps);
        $index = array_search($key, $keys, true);

        if ($index === false || !isset($keys[$index + 1])) {
            return null;
        }

        return $this->steps[$keys[$index + 1]];
    }

    public function completeHandler(): array|Closure|null
    {
        return $this->onComplete;
    }

    public function cancelHandler(): array|Closure|null
    {
        return $this->onCancel;
    }

    /**
     * @return string[] Cancel commands, falling back to the configured default.
     */
    public function cancelCommands(): array
    {
        return $this->cancelCommands
            ?? (array) config('laragram.scenes.cancel_commands', ['/cancel']);
    }

    public function backEnabled(): bool
    {
        return !empty($this->backCommands);
    }

    /**
     * @return string[]
     */
    public function backCommands(): array
    {
        return $this->backCommands ?? [];
    }

    public function timeoutMinutes(): ?int
    {
        return $this->ttl;
    }

    public function timeoutHandler(): array|Closure|null
    {
        return $this->onTimeout;
    }
}
