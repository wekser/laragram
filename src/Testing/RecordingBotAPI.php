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

namespace Wekser\Laragram\Testing;

use Wekser\Laragram\BotAPI;

/**
 * A BotAPI test double that records every outbound call instead of hitting
 * Telegram. Used by InteractsWithBot to capture the messages a bot sends so
 * they can be asserted on, and by ResponseDispatcher tests to simulate a
 * delivery failure on a given method (pass $throwOn / $exception).
 *
 * Extends BotAPI with a no-arg constructor (skips token/transport setup) and
 * overrides __call() — PHPUnit cannot reliably mock BotAPI's magic methods.
 */
class RecordingBotAPI extends BotAPI
{
    /**
     * Every recorded call, in order.
     *
     * @var array<int, array{method: string, params: array<string, mixed>}>
     */
    public array $calls = [];

    /**
     * @param string|null    $throwOn   Method name that should throw when called.
     * @param \Throwable|null $exception Exception to throw for that method.
     */
    public function __construct(
        private readonly ?string $throwOn = null,
        private readonly ?\Throwable $exception = null,
    ) {}

    public function __call(string $method, array $arguments): mixed
    {
        $this->calls[] = [
            'method' => $method,
            'params' => $arguments[0] ?? [],
        ];

        if ($this->throwOn === $method && $this->exception !== null) {
            throw $this->exception;
        }

        return [];
    }
}
