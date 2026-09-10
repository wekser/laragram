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
 * Validation rules for an InlineKeyboardMarkup, shared by the builders (which
 * refuse to create an unusable button) and by BotClient (which drops one that
 * reached it anyway, from a hand-built array).
 *
 * Telegram requires every InlineKeyboardButton to carry a non-empty label *and*
 * exactly one action field. A button with only 'text' — the usual cause being a
 * url/callback_data that came out null or empty — makes the API reject the whole
 * request with "can't parse InlineKeyboardButton: Text buttons are not allowed
 * in the inline keyboard", so one broken button costs the entire message.
 */
final class ReplyMarkup
{
    /**
     * Action fields that make an inline button usable, mapped to whether an
     * empty string is still a meaningful value for that field.
     *
     * Only the switch_inline_query pair accepts one: an empty query inserts just
     * the bot's username into the input field, which is a documented use.
     */
    private const INLINE_ACTIONS = [
        'callback_data'                    => false,
        'url'                              => false,
        'web_app'                          => false,
        'login_url'                        => false,
        'switch_inline_query'              => true,
        'switch_inline_query_current_chat' => true,
        'switch_inline_query_chosen_chat'  => false,
        'copy_text'                        => false,
        'callback_game'                    => false,
        'pay'                              => false,
    ];

    /**
     * Longest button description written into an exception or a log entry.
     */
    private const DESCRIPTION_LIMIT = 80;

    /**
     * Would Telegram accept this inline button?
     *
     * @param array<string, mixed> $button
     */
    public static function isUsableInlineButton(array $button): bool
    {
        return self::hasText($button) && self::hasAction($button);
    }

    /**
     * Guard a button at the point it is built, so the mistake is reported with
     * the offending label instead of surfacing later as a Telegram 400 on the
     * whole message.
     *
     * @param array<string, mixed> $button
     * @throws \InvalidArgumentException
     */
    public static function assertUsableInlineButton(array $button): void
    {
        if (!self::hasText($button)) {
            throw new \InvalidArgumentException(
                'An inline keyboard button needs a non-empty text label; Telegram rejects the whole message otherwise.'
            );
        }

        if (!self::hasAction($button)) {
            throw new \InvalidArgumentException(sprintf(
                'Inline keyboard button %s carries no action — one of %s must be set to a non-empty value '
                . '(a null or empty url/callback_data is the usual cause). Telegram would reject the whole '
                . 'message with "Text buttons are not allowed in the inline keyboard".',
                self::describe($button),
                implode(', ', array_keys(self::INLINE_ACTIONS)),
            ));
        }
    }

    /**
     * Strip every unusable button from an inline keyboard, then every row left
     * empty. Rows keep their order; surviving buttons keep theirs.
     *
     * A markup that is not an inline keyboard (ReplyKeyboard, ForceReply,
     * ReplyKeyboardRemove) is returned untouched — a reply-keyboard button
     * legitimately carries nothing but its text.
     *
     * @param array<string, mixed>  $markup
     * @param array<int, string>   &$dropped Descriptions of the removed buttons.
     * @return array<string, mixed>
     */
    public static function sanitize(array $markup, array &$dropped = []): array
    {
        if (!isset($markup['inline_keyboard']) || !is_array($markup['inline_keyboard'])) {
            return $markup;
        }

        $rows = [];

        foreach ($markup['inline_keyboard'] as $row) {
            if (!is_array($row)) {
                $dropped[] = self::describe($row);
                continue;
            }

            $kept = [];

            foreach ($row as $button) {
                if (is_array($button) && self::isUsableInlineButton($button)) {
                    $kept[] = $button;
                    continue;
                }

                $dropped[] = self::describe($button);
            }

            if ($kept !== []) {
                $rows[] = array_values($kept);
            }
        }

        $markup['inline_keyboard'] = $rows;

        return $markup;
    }

    /**
     * A short, log-safe description of a button: its label when it has one,
     * otherwise the raw payload, truncated.
     */
    public static function describe(mixed $button): string
    {
        if (is_array($button)) {
            $text = $button['text'] ?? null;

            if ((is_string($text) || is_numeric($text)) && trim((string) $text) !== '') {
                return '"' . self::truncate(trim((string) $text)) . '"';
            }
        }

        $encoded = json_encode($button, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $encoded === false ? '(unencodable button)' : self::truncate($encoded);
    }

    /**
     * Does the button carry a non-blank label?
     *
     * @param array<string, mixed> $button
     */
    private static function hasText(array $button): bool
    {
        $text = $button['text'] ?? null;

        if (!is_string($text) && !is_numeric($text)) {
            return false;
        }

        return trim((string) $text) !== '';
    }

    /**
     * Does the button carry at least one usable action field?
     *
     * @param array<string, mixed> $button
     */
    private static function hasAction(array $button): bool
    {
        foreach (self::INLINE_ACTIONS as $key => $emptyAllowed) {
            if (array_key_exists($key, $button) && self::isUsableAction($button[$key], $emptyAllowed)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Is this action field's value one Telegram can act on?
     */
    private static function isUsableAction(mixed $value, bool $emptyAllowed): bool
    {
        if ($value === null) {
            return false;
        }

        // 'pay' => false is the field being present but switched off.
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return $emptyAllowed || trim($value) !== '';
        }

        // callback_game is an empty object by definition.
        if (is_object($value)) {
            return true;
        }

        if (is_array($value)) {
            if ($value === []) {
                return $emptyAllowed;
            }

            // web_app / login_url / copy_text wrap the value that actually
            // matters — an empty one is the same nullable-value bug one level in.
            foreach (['url', 'text'] as $nested) {
                if (array_key_exists($nested, $value)
                    && (!is_string($value[$nested]) || trim($value[$nested]) === '')) {
                    return false;
                }
            }

            return true;
        }

        return true;
    }

    /**
     * Bound a description so a log line stays readable.
     */
    private static function truncate(string $value): string
    {
        return mb_strlen($value) > self::DESCRIPTION_LIMIT
            ? mb_substr($value, 0, self::DESCRIPTION_LIMIT) . '…'
            : $value;
    }
}
