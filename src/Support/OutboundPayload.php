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

namespace Wekser\Laragram\Support;

/**
 * Helpers for turning a formed response payload into an outbound Bot API call.
 *
 * A payload carries the Telegram method name under 'method'; the remaining keys
 * are the API parameters, minus any internal bookkeeping keys (prefixed with
 * '_', e.g. '_escaped'). Shared by ResponseDispatcher (the webhook/poll path)
 * and PendingBroadcast (the synchronous broadcast path) so both strip identically.
 */
final class OutboundPayload
{
    /**
     * The Telegram method to call, or null when the payload carries none.
     *
     * @param array<string, mixed> $payload
     */
    public static function method(array $payload): ?string
    {
        $method = $payload['method'] ?? null;

        return empty($method) ? null : (string) $method;
    }

    /**
     * The API parameters: every key except 'method' and '_'-prefixed bookkeeping.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public static function params(array $payload): array
    {
        return array_filter(
            $payload,
            static fn (string $key): bool => $key !== 'method' && !str_starts_with($key, '_'),
            ARRAY_FILTER_USE_KEY,
        );
    }
}
