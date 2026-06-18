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
use Illuminate\Support\Facades\Log;

class VerifyTelegramSecret
{
    public function handle(Request $request, Closure $next): mixed
    {
        if (!config('laragram.security.verify_secret', true)) {
            return $next($request);
        }

        $expected = (string) config('laragram.telegram.secret');
        $provided = (string) $request->headers->get('X-Telegram-Bot-Api-Secret-Token', '');

        if ($expected === '') {
            Log::error(
                'laragram: verify_secret is enabled but LARAGRAM_WEBHOOK_SECRET is empty — ' .
                'configure it or set LARAGRAM_VERIFY_SECRET=false',
                ['ip' => $request->ip()]
            );

            return response()->json(['message' => 'Webhook secret not configured.'], 500);
        }

        if (!hash_equals($expected, $provided)) {
            Log::warning('laragram: invalid webhook secret token', [
                'ip' => $request->ip(),
            ]);

            return response()->json(['message' => 'Invalid webhook signature.'], 401);
        }

        return $next($request);
    }
}
