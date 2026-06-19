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

namespace Wekser\Laragram\Enums;

/**
 * Optional visual color of a keyboard button.
 *
 * Added in Bot API 9.4 to both InlineKeyboardButton and KeyboardButton.
 * If omitted, an app-specific default style is used.
 *
 * @package Wekser\Laragram\Enums
 */
enum ButtonStyle: string
{
    case Primary = 'primary';   // blue
    case Success = 'success';   // green
    case Danger  = 'danger';    // red

    /**
     * Normalize a string or enum case to a valid Telegram style value.
     *
     * @throws \InvalidArgumentException When the string is not a known style.
     */
    public static function normalize(self|string $style): string
    {
        if ($style instanceof self) {
            return $style->value;
        }

        return (self::tryFrom($style) ?? throw new \InvalidArgumentException(
            sprintf(
                "Invalid button style '%s'. Allowed: %s.",
                $style,
                implode(', ', array_map(fn (self $c) => $c->value, self::cases())),
            )
        ))->value;
    }

    /**
     * Merge the optional Bot API 9.4 visual attributes into a button payload.
     *
     * Centralizes the `style` / `icon_custom_emoji_id` field names so every
     * keyboard builder and view-state class shares one definition; both keys
     * are omitted when their value is null.
     *
     * @param array            $button The button payload to decorate.
     * @param self|string|null $style  Optional color (validated via normalize()).
     * @param string|null      $icon   Optional custom emoji id.
     */
    public static function decorate(array $button, self|string|null $style, ?string $icon): array
    {
        if ($style !== null) $button['style']                = self::normalize($style);
        if ($icon !== null)  $button['icon_custom_emoji_id'] = $icon;

        return $button;
    }
}
