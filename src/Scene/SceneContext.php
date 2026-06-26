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

use Illuminate\Support\Arr;
use Wekser\Laragram\BotRequest;
use Wekser\Laragram\Facades\BotAuth;

/**
 * Passed to step prompts (ask closures) and to onComplete / onCancel handlers.
 *
 * Exposes the answers collected so far plus the underlying BotRequest and the
 * authenticated user, so handlers have everything they need without reaching
 * into globals.
 *
 *   ->ask(fn (SceneContext $ctx) => BotResponse::text("Size {$ctx->get('size')}?"))
 *   ->onComplete(fn (SceneContext $ctx) => Order::create($ctx->all()))
 */
final class SceneContext
{
    /**
     * @param array<string, mixed> $data Answers collected so far, keyed by step.
     */
    public function __construct(
        private readonly BotRequest $request,
        private readonly array      $data = [],
    ) {}

    /**
     * Get a collected answer by step key (dot notation supported).
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->data, $key, $default);
    }

    /**
     * All answers collected so far.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->data;
    }

    /**
     * Whether an answer for the given step key has been collected.
     */
    public function has(string $key): bool
    {
        return Arr::has($this->data, $key);
    }

    /**
     * The underlying request for the current update.
     */
    public function request(): BotRequest
    {
        return $this->request;
    }

    /**
     * The authenticated user, if any.
     */
    public function user(): mixed
    {
        return BotAuth::user();
    }
}
