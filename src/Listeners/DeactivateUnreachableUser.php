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

namespace Wekser\Laragram\Listeners;

use Wekser\Laragram\Events\BotExceptionHandled;
use Wekser\Laragram\Exceptions\BotBlockedException;
use Wekser\Laragram\Exceptions\ChatNotFoundException;
use Wekser\Laragram\Exceptions\UserDeactivatedException;

/**
 * Marks a user inactive the first time a send to them fails because they are
 * unreachable (blocked the bot, deactivated their account, or the chat is gone).
 *
 * Bound to BotExceptionHandled, so it reacts to every swallowed terminal error
 * — whether from a normal reply, a queued update, or a broadcast — and keeps the
 * active user set self-cleaning. Opt-out via laragram.broadcast.deactivate_unreachable.
 * No-op under the "array" auth driver (no persisted users) and must never throw,
 * to preserve ExceptionHandler's swallow contract.
 */
class DeactivateUnreachableUser
{
    public function handle(BotExceptionHandled $event): void
    {
        if (!$event->terminal) {
            return;
        }

        if (!config('laragram.broadcast.deactivate_unreachable', true)) {
            return;
        }

        if (config('laragram.auth.driver') !== 'database') {
            return;
        }

        $uid = $this->resolveUid($event->exception);

        // A non-positive id is never a real private-chat uid: 0 is the sentinel
        // for a missing context and negative ids belong to groups/channels (a
        // failed group send must not deactivate a coincidentally-matching user).
        if ($uid === null || $uid <= 0) {
            return;
        }

        try {
            /** @var class-string<\Wekser\Laragram\Models\User> $model */
            $model = config('laragram.auth.user.model');

            $user = $model::where('uid', $uid)->where('is_active', true)->first();

            $user?->deactivate();
        } catch (\Throwable) {
            // An observability-driven cleanup must never turn a handled error fatal.
        }
    }

    /**
     * Extract the affected Telegram user id from an unreachable-user exception.
     */
    protected function resolveUid(\Throwable $exception): ?int
    {
        return match (true) {
            $exception instanceof BotBlockedException,
            $exception instanceof UserDeactivatedException => $exception->getUserId(),
            $exception instanceof ChatNotFoundException    => (int) $exception->getChatId(),
            default                                        => null,
        };
    }
}
