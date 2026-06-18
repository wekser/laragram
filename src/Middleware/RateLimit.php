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
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Wekser\Laragram\BotAuth;

class RateLimit
{
    public function __construct(protected RateLimiter $limiter)
    {
    }

    public function handle(Request $request, Closure $next): mixed
    {
        $maxAttempts = (int) config('laragram.rate.max_attempts', 60);
        $decaySeconds = (int) config('laragram.rate.decay_seconds', 60);

        $key = $this->resolveKey($request);

        if ($this->limiter->tooManyAttempts($key, $maxAttempts)) {
            $retryAfter = $this->limiter->availableIn($key);
            return response()->json([
                'message'     => 'Too Many Requests.',
                'retry_after' => $retryAfter,
            ], 429);
        }

        $this->limiter->hit($key, $decaySeconds);

        return $next($request);
    }

    protected function resolveKey(Request $request): string
    {
        $bot    = (string) config('laragram.telegram.token');
        $userId = $this->extractUserId($request->all());
        $key    = $userId !== null ? "user:{$userId}" : 'ip:' . ($request->ip() ?? 'unknown');
        return sha1("laragram:webhook:{$bot}:{$key}");
    }

    private function extractUserId(array $payload): ?int
    {
        $from = BotAuth::findFromInPayload($payload);
        return $from !== null ? (int) $from['id'] : null;
    }
}

