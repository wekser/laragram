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

namespace Wekser\Laragram\Middleware;

use Closure;
use Illuminate\Http\Request;
use Wekser\Laragram\BotAuth;

class CheckAuth
{
    /**
     * Handle an incoming request.
     *
     * Updates the bot cannot act on — authored by another bot (or by itself, as
     * with its own channel posts), or carrying no sender at all — are skipped
     * without invoking the pipeline. They are still acknowledged with 200:
     * Telegram redelivers any update the webhook answers with a non-2xx status,
     * so rejecting a routine bot-authored update loops it back indefinitely and
     * stalls the pending-update queue behind it. Request authenticity is already
     * enforced upstream by VerifyTelegramSecret.
     *
     * A skip is not logged: a bot sitting in a channel or group receives such
     * updates continuously without anyone talking to it.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $payload = $request->all();
        $user    = BotAuth::findFromInPayload($payload);

        if (!empty($user) && !($user['is_bot'] ?? false)) {
            return $next($request);
        }

        // Allow senderless update types (e.g. poll) to pass through without a sender.
        if (empty($user) && BotAuth::isSenderlessPayload($payload)) {
            return $next($request);
        }

        return response('OK', 200);
    }
}
