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

use Wekser\Laragram\Support\Reaction;

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
        string $chatType  = 'private',
        ?int   $threadId  = null,
    ): array {
        return [
            'update_id' => self::nextId(),
            'message'   => array_merge([
                'message_id' => 1,
                'from'       => self::sender($userId, $firstName, $lastName, $username, $language),
                'chat'       => ['id' => $chatId, 'type' => $chatType],
                'date'       => time(),
                'text'       => $text,
            ], self::topicFields($threadId)),
        ];
    }

    /**
     * Create a text message update posted inside a forum topic of a supergroup.
     */
    public static function topicMessage(
        string $text,
        int    $threadId  = 42,
        int    $chatId    = -1_000_000,
        int    $userId    = 100,
        string $firstName = 'Test',
    ): array {
        return self::message(
            text: $text,
            userId: $userId,
            firstName: $firstName,
            chatId: $chatId,
            chatType: 'supergroup',
            threadId: $threadId,
        );
    }

    /**
     * The pair of fields Telegram sets on a message inside a forum topic.
     *
     * A General-topic message carries neither, which is why $threadId = null
     * produces an ordinary (non-topic) message.
     *
     * @return array<string, mixed>
     */
    private static function topicFields(?int $threadId): array
    {
        return $threadId === null ? [] : [
            'message_thread_id' => $threadId,
            'is_topic_message'  => true,
        ];
    }

    /**
     * Create a text message update coming from a group/supergroup chat.
     *
     * The chat id differs from the sender id (as in real groups), so per-(user,
     * chat) state and outbound targeting can be exercised.
     */
    public static function groupMessage(
        string $text,
        int    $chatId    = -1_000_000,
        int    $userId    = 100,
        string $firstName = 'Test',
        string $chatType  = 'supergroup',
        ?int   $threadId  = null,
    ): array {
        return self::message(
            text: $text,
            userId: $userId,
            firstName: $firstName,
            chatId: $chatId,
            chatType: $chatType,
            threadId: $threadId,
        );
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
        string $chatType  = 'private',
        ?int   $threadId  = null,
    ): array {
        return [
            'update_id'      => self::nextId(),
            'callback_query' => [
                'id'      => (string) random_int(100_000, 999_999),
                'from'    => self::sender($userId, $firstName, $lastName, $username, $language),
                'message' => array_merge([
                    'message_id' => 1,
                    'chat'       => ['id' => $chatId, 'type' => $chatType],
                    'date'       => time(),
                ], self::topicFields($threadId)),
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
     * Create a chosen_inline_result update (user picked one of the bot's
     * inline results).
     */
    public static function chosenInlineResult(
        string $resultId  = '1',
        string $query     = '',
        int    $userId    = 100,
        string $firstName = 'Test',
        string $lastName  = '',
        string $username  = 'test_user',
        string $language  = 'en',
    ): array {
        return [
            'update_id'            => self::nextId(),
            'chosen_inline_result' => [
                'result_id' => $resultId,
                'from'      => self::sender($userId, $firstName, $lastName, $username, $language),
                'query'     => $query,
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

    /**
     * Create a pre_checkout_query update (payment confirmation step).
     */
    public static function preCheckoutQuery(
        string $payload     = 'order_1',
        int    $totalAmount = 500,
        string $currency    = 'XTR',
        int    $userId      = 100,
        string $firstName   = 'Test',
        string $lastName    = '',
        string $username    = 'test_user',
        string $language    = 'en',
    ): array {
        return [
            'update_id'          => self::nextId(),
            'pre_checkout_query' => [
                'id'              => (string) random_int(100_000, 999_999),
                'from'            => self::sender($userId, $firstName, $lastName, $username, $language),
                'currency'        => $currency,
                'total_amount'    => $totalAmount,
                'invoice_payload' => $payload,
            ],
        ];
    }

    /**
     * Create a shipping_query update (flexible-price delivery step).
     */
    public static function shippingQuery(
        string $payload   = 'order_1',
        int    $userId    = 100,
        string $firstName = 'Test',
        string $lastName  = '',
        string $username  = 'test_user',
        string $language  = 'en',
    ): array {
        return [
            'update_id'      => self::nextId(),
            'shipping_query' => [
                'id'              => (string) random_int(100_000, 999_999),
                'from'            => self::sender($userId, $firstName, $lastName, $username, $language),
                'invoice_payload' => $payload,
                'shipping_address' => [
                    'country_code' => 'US',
                    'state'        => 'CA',
                    'city'         => 'San Francisco',
                    'street_line1' => '1 Market St',
                    'street_line2' => '',
                    'post_code'    => '94105',
                ],
            ],
        ];
    }

    /**
     * Create a message update carrying a successful_payment (completed order).
     */
    public static function successfulPaymentMessage(
        string $payload     = 'order_1',
        int    $totalAmount = 500,
        string $currency    = 'XTR',
        string $chargeId    = 'charge_abc123',
        int    $userId      = 100,
        string $firstName   = 'Test',
        int    $chatId      = 100,
    ): array {
        return [
            'update_id' => self::nextId(),
            'message'   => [
                'message_id' => 1,
                'from'       => self::sender($userId, $firstName),
                'chat'       => ['id' => $chatId, 'type' => 'private'],
                'date'       => time(),
                'successful_payment' => [
                    'currency'                     => $currency,
                    'total_amount'                 => $totalAmount,
                    'invoice_payload'              => $payload,
                    'telegram_payment_charge_id'   => $chargeId,
                    'provider_payment_charge_id'   => '',
                ],
            ],
        ];
    }

    /**
     * Create a message_reaction update (user changed their reaction on a message).
     *
     * $new / $old accept an emoji string, a list of emoji strings, or raw
     * ReactionType arrays. Pass $anonymous = true to emit an actor_chat instead
     * of a user (a reaction made on behalf of a chat).
     */
    public static function messageReaction(
        string|array $new       = '👍',
        string|array $old       = [],
        int          $userId    = 100,
        string       $firstName = 'Test',
        int          $chatId    = 100,
        int          $messageId = 1,
        bool         $anonymous = false,
    ): array {
        $actor = $anonymous
            ? ['actor_chat' => ['id' => $chatId, 'type' => 'channel']]
            : ['user' => self::sender($userId, $firstName)];

        return [
            'update_id'        => self::nextId(),
            'message_reaction' => array_merge([
                'chat'         => ['id' => $chatId, 'type' => 'private'],
                'message_id'   => $messageId,
                'date'         => time(),
                'old_reaction' => self::reactionTypes($old),
                'new_reaction' => self::reactionTypes($new),
            ], $actor),
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

    /**
     * Normalize emoji string(s) / raw ReactionType arrays into a ReactionType list.
     *
     * @param string|array<int, string|array<string, mixed>> $reaction
     * @return array<int, array<string, mixed>>
     */
    private static function reactionTypes(string|array $reaction): array
    {
        return Reaction::normalize($reaction);
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
