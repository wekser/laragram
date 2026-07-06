<?php

namespace Wekser\Laragram\Support;

/**
 * Normalizes reaction input into Telegram ReactionType arrays.
 */
class Reaction
{
    /**
     * Normalize an emoji string, a list of emoji strings, or raw ReactionType
     * arrays into a Telegram ReactionType list. String items become
     * ['type' => 'emoji', 'emoji' => ...]; array items pass through verbatim
     * (custom_emoji / paid types).
     *
     * @param string|array<int, string|array<string, mixed>> $reaction
     * @return array<int, array<string, mixed>>
     */
    public static function normalize(string|array $reaction): array
    {
        return array_map(
            static fn (string|array $item): array => is_string($item)
                ? ['type' => 'emoji', 'emoji' => $item]
                : $item,
            is_string($reaction) ? [$reaction] : array_values($reaction)
        );
    }
}
