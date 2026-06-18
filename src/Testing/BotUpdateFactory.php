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

/**
 * Factory for realistic Telegram update arrays used in feature tests.
 *
 * Usage:
 *
 *   $update = BotUpdateFactory::message('/start');
 *   $update = BotUpdateFactory::callbackQuery('btn_action');
 *   $update = BotUpdateFactory::message('Hello', userId: 42, firstName: 'John');
 *
 *   BotUpdateFactory::reset(); // reset counter between test cases
 */
class BotUpdateFactory
{
    private static int $updateIdCounter = 1;

    // -------------------------------------------------------------------------
    // Update type factories
    // -------------------------------------------------------------------------

    /**
     * Create a text message update.
     */
    public static function message(
        string $text,
        int    $userId    = 100,
        string $firstName = 'Test',
        string $lastName  = '',
        string $username  = 'test_user',
        string $language  = 'en',
        int    $chatId    = 100,
    ): array {
        return [
            'update_id' => self::nextId(),
            'message'   => [
                'message_id' => 1,
                'from'       => self::sender($userId, $firstName, $lastName, $username, $language),
                'chat'       => ['id' => $chatId, 'type' => 'private'],
                'date'       => time(),
                'text'       => $text,
            ],
        ];
    }

    /**
     * Create a callback query update (inline keyboard button press).
     */
    public static function callbackQuery(
        string $data,
        int    $userId    = 100,
        string $firstName = 'Test',
        string $lastName  = '',
        string $username  = 'test_user',
        string $language  = 'en',
        int    $chatId    = 100,
    ): array {
        return [
            'update_id'      => self::nextId(),
            'callback_query' => [
                'id'      => (string) random_int(100_000, 999_999),
                'from'    => self::sender($userId, $firstName, $lastName, $username, $language),
                'message' => [
                    'message_id' => 1,
                    'chat'       => ['id' => $chatId, 'type' => 'private'],
                    'date'       => time(),
                ],
                'data' => $data,
            ],
        ];
    }

    /**
     * Create an inline query update.
     */
    public static function inlineQuery(
        string $query,
        int    $userId    = 100,
        string $firstName = 'Test',
        string $lastName  = '',
        string $username  = 'test_user',
        string $language  = 'en',
    ): array {
        return [
            'update_id'    => self::nextId(),
            'inline_query' => [
                'id'     => (string) random_int(100_000, 999_999),
                'from'   => self::sender($userId, $firstName, $lastName, $username, $language),
                'query'  => $query,
                'offset' => '',
            ],
        ];
    }

    /**
     * Create an edited message update.
     */
    public static function editedMessage(
        string $text,
        int    $userId    = 100,
        string $firstName = 'Test',
        int    $chatId    = 100,
    ): array {
        return [
            'update_id'      => self::nextId(),
            'edited_message' => [
                'message_id'      => 1,
                'from'            => self::sender($userId, $firstName),
                'chat'            => ['id' => $chatId, 'type' => 'private'],
                'date'            => time(),
                'edit_date'       => time(),
                'text'            => $text,
            ],
        ];
    }

    /**
     * Create a channel post update.
     */
    public static function channelPost(
        string $text,
        int    $chatId = -100_000,
    ): array {
        return [
            'update_id'    => self::nextId(),
            'channel_post' => [
                'message_id' => 1,
                'sender_chat' => ['id' => $chatId, 'type' => 'channel'],
                'chat'       => ['id' => $chatId, 'type' => 'channel'],
                'date'       => time(),
                'text'       => $text,
                'from'       => self::sender(0, 'Channel'),
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Utility
    // -------------------------------------------------------------------------

    /**
     * Reset the update_id counter (call in setUp() between test cases).
     */
    public static function reset(): void
    {
        self::$updateIdCounter = 1;
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    private static function nextId(): int
    {
        return self::$updateIdCounter++;
    }

    private static function sender(
        int    $id,
        string $firstName,
        string $lastName  = '',
        string $username  = 'test_user',
        string $language  = 'en',
    ): array {
        return array_filter([
            'id'            => $id,
            'is_bot'        => false,
            'first_name'    => $firstName,
            'last_name'     => $lastName ?: null,
            'username'      => $username ?: null,
            'language_code' => $language,
        ], fn ($v) => $v !== null && $v !== false);
    }
}
