<?php

/*
 * This file is part of Laragram.
 *
 * (c) Sergey Lapin <hello@wekser.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Wekser\Laragram\Support;

use Wekser\Laragram\Facades\BotAuth;

trait ApiMethods
{
    /**
     * A simple method for testing your bot's auth token.
     *
     * @link https://core.telegram.org/bots/api#getMe
     *
     * @return array
     */
    public function getMe()
    {
        return $this->request('getMe');
    }

    /**
     * Send text message.
     *
     * @link https://core.telegram.org/bots/api#sendMessage
     *
     * @param array $params
     *
     * @var int|string $params ['chat_id']
     * @var string $params ['text']
     * @var string $params ['parse_mode']
     * @var bool $params ['disable_web_page_preview']
     * @var bool $params ['disable_notification']
     * @var int $params ['reply_to_message_id']
     * @var string $params ['reply_markup']
     *
     * @return array
     */
    public function sendMessage(array $params)
    {
        return $this->request('sendMessage', $this->setParameters($params, 'chat_id'));
    }

    /**
     * Forward message of any kind.
     *
     * @link https://core.telegram.org/bots/api#forwardMessage
     *
     * @param array $params
     *
     * @var int|string $params ['chat_id']
     * @var int $params ['from_chat_id']
     * @var bool $params ['disable_notification']
     * @var int $params ['message_id']
     *
     * @return array
     */
    public function forwardMessage(array $params)
    {
        return $this->request('forwardMessage', $this->setParameters($params, 'chat_id'));
    }

    /**
     * Send photo.
     *
     * @link https://core.telegram.org/bots/api#sendPhoto
     *
     * @param array $params
     *
     * @var int|string $params ['chat_id']
     * @var string $params ['photo']
     * @var string $params ['caption']
     * @var bool $params ['parse_mode']
     * @var bool $params ['disable_notification']
     * @var int $params ['reply_to_message_id']
     * @var string $params ['reply_markup']
     *
     * @return array
     */
    public function sendPhoto(array $params)
    {
        return $this->request('sendPhoto', $this->setParameters($params, 'chat_id'), true);
    }

    /**
     * Send regular audio file.
     *
     * @link https://core.telegram.org/bots/api#sendAudio
     *
     * @param array $params
     *
     * @var int|string $params ['chat_id']
     * @var string $params ['audio']
     * @var string $params ['caption']
     * @var bool $params ['parse_mode']
     * @var int $params ['duration']
     * @var string $params ['performer']
     * @var string $params ['title']
     * @var string $params ['thumb']
     * @var bool $params ['disable_notification']
     * @var int $params ['reply_to_message_id']
     * @var string $params ['reply_markup']
     *
     * @return array
     */
    public function sendAudio(array $params)
    {
        return $this->request('sendAudio', $this->setParameters($params, 'chat_id'), true);
    }

    /**
     * Send general file.
     *
     * @link https://core.telegram.org/bots/api#sendDocument
     *
     * @param array $params
     *
     * @var int|string $params ['chat_id']
     * @var string $params ['document']
     * @var string $params ['thumb']
     * @var string $params ['caption']
     * @var bool $params ['parse_mode']
     * @var bool $params ['disable_notification']
     * @var int $params ['reply_to_message_id']
     * @var string $params ['reply_markup']
     *
     * @return array
     */
    public function sendDocument(array $params)
    {
        return $this->request('sendDocument', $this->setParameters($params, 'chat_id'), true);
    }

    /**
     * Send video file.
     *
     * @link https://core.telegram.org/bots/api#sendVideo
     *
     * @param array $params
     *
     * @var int|string $params ['chat_id']
     * @var string $params ['video']
     * @var int $params ['duration']
     * @var int $params ['width']
     * @var int $params ['height']
     * @var string $params ['thumb']
     * @var string $params ['caption']
     * @var bool $params ['parse_mode']
     * @var bool $params ['supports_streaming']
     * @var bool $params ['disable_notification']
     * @var int $params ['reply_to_message_id']
     * @var string $params ['reply_markup']
     *
     * @return array
     */
    public function sendVideo(array $params)
    {
        return $this->request('sendVideo', $this->setParameters($params, 'chat_id'), true);
    }

    /**
     * Send animation file (GIF or H.264/MPEG-4 AVC video without sound).
     *
     * @link https://core.telegram.org/bots/api#sendAnimation
     *
     * @param array $params
     *
     * @var int|string $params ['chat_id']
     * @var string $params ['animation']
     * @var int $params ['duration']
     * @var int $params ['width']
     * @var int $params ['height']
     * @var string $params ['thumb']
     * @var string $params ['caption']
     * @var bool $params ['parse_mode']
     * @var bool $params ['disable_notification']
     * @var int $params ['reply_to_message_id']
     * @var string $params ['reply_markup']
     *
     * @return array
     */
    public function sendAnimation(array $params)
    {
        return $this->request('sendAnimation', $this->setParameters($params, 'chat_id'), true);
    }

    /**
     * Send voice audio file.
     *
     * @link https://core.telegram.org/bots/api#sendVoice
     *
     * @param array $params
     *
     * @var int|string $params ['chat_id']
     * @var string $params ['voice']
     * @var string $params ['caption']
     * @var bool $params ['parse_mode']
     * @var int $params ['duration']
     * @var bool $params ['disable_notification']
     * @var int $params ['reply_to_message_id']
     * @var string $params ['reply_markup']
     *
     * @return array
     */
    public function sendVoice(array $params)
    {
        return $this->request('sendVoice', $this->setParameters($params, 'chat_id'), true);
    }

    /**
     * Send rounded square mp4 video file of up to 1 minute long.
     *
     * @link https://core.telegram.org/bots/api#sendVideoNote
     *
     * @param array $params
     *
     * @var int|string $params ['chat_id']
     * @var string $params ['video_note']
     * @var int $params ['duration']
     * @var int $params ['length']
     * @var string $params ['thumb']
     * @var bool $params ['disable_notification']
     * @var int $params ['reply_to_message_id']
     * @var string $params ['reply_markup']
     *
     * @return array
     */
    public function sendVideoNote(array $params)
    {
        return $this->request('sendVideoNote', $this->setParameters($params, 'chat_id'), true);
    }

    /**
     * Send point on the map.
     *
     * @link https://core.telegram.org/bots/api#sendLocation
     *
     * @param array $params
     *
     * @var int|string $params ['chat_id']
     * @var float $params ['latitude']
     * @var float $params ['longitude']
     * @var int $params ['live_period']
     * @var bool $params ['disable_notification']
     * @var int $params ['reply_to_message_id']
     *
     * @return array
     */
    public function sendLocation(array $params)
    {
        return $this->request('sendLocation', $this->setParameters($params, 'chat_id'));
    }

    /**
     * Edit live location messages sent by the bot or via the bot.
     *
     * @link https://core.telegram.org/bots/api#editMessageLiveLocation
     *
     * @param array $params
     *
     * @var int|string $params ['chat_id']
     * @var int $params ['message_id']
     * @var string $params ['inline_message_id']
     * @var int $params ['latitude']
     * @var float $params ['longitude']
     * @var string $params ['reply_markup']
     *
     * @return array
     */
    public function editMessageLiveLocation(array $params)
    {
        return $this->request('editMessageLiveLocation', $this->setParameters($params, 'chat_id'));
    }

    /**
     * Stop updating a live location message sent by the bot or via the bot.
     *
     * @link https://core.telegram.org/bots/api#stopMessageLiveLocation
     *
     * @param array $params
     *
     * @var int|string $params ['chat_id']
     * @var int $params ['message_id']
     * @var string $params ['inline_message_id']
     * @var string $params ['reply_markup']
     *
     * @return array
     */
    public function stopMessageLiveLocation(array $params)
    {
        return $this->request('stopMessageLiveLocation', $this->setParameters($params, 'chat_id'));
    }

    /**
     * Send information about a venue.
     *
     * @link https://core.telegram.org/bots/api#sendVenue
     *
     * @param array $params
     *
     * @var int|string $params ['chat_id']
     * @var float $params ['latitude']
     * @var float $params ['longitude']
     * @var string $params ['title']
     * @var string $params ['address']
     * @var string $params ['foursquare_id']
     * @var string $params ['foursquare_type']
     * @var bool $params ['disable_notification']
     * @var int $params ['reply_to_message_id']
     * @var string $params ['reply_markup']
     *
     * @return array
     */
    public function sendVenue(array $params)
    {
        return $this->request('sendVenue', $this->setParameters($params, 'chat_id'));
    }

    /**
     * Send phone contact.
     *
     * @link https://core.telegram.org/bots/api#sendContact
     *
     * @param array $params
     *
     * @var int|string $params ['chat_id']
     * @var string $params ['phone_number']
     * @var string $params ['first_name']
     * @var string $params ['last_name']
     * @var string $params ['vcard']
     * @var bool $params ['disable_notification']
     * @var int $params ['reply_to_message_id']
     * @var string $params ['reply_markup']
     *
     * @return array
     */
    public function sendContact(array $params)
    {
        return $this->request('sendContact', $this->setParameters($params, 'chat_id'));
    }

    /**
     * Broadcast a Chat Action.
     *
     * @link https://core.telegram.org/bots/api#sendChatAction
     *
     * @param array $params
     *
     * @var int|string $params ['chat_id']
     * @var string $params ['action']
     *
     * @return array
     */
    public function sendChatAction(array $params)
    {
        return $this->request('sendChatAction', $this->setParameters($params, 'chat_id'));
    }

    /**
     * Returns a list of profile pictures for a user.
     *
     * @link https://core.telegram.org/bots/api#getUserProfilePhotos
     *
     * @param array $params
     *
     * @var int $params ['user_id']
     * @var int $params ['offset']
     * @var int $params ['limit']
     *
     * @return array
     */
    public function getUserProfilePhotos(array $params)
    {
        return $this->request('getUserProfilePhotos', $this->setParameters($params, 'user_id'));
    }

    /**
     * Returns basic info about a file and prepare it for downloading.
     *
     * @link https://core.telegram.org/bots/api#getFile
     *
     * @param array $params
     *
     * @var string $params ['file_id']
     *
     * @return array
     */
    public function getFile(array $params)
    {
        return $this->request('getFile', $params);
    }

    /**
     * Get up to date information about the chat.
     *
     * @link https://core.telegram.org/bots/api#getChat
     *
     * @param array $params
     *
     * @var string|int $params ['chat_id']
     *
     * @return array
     */
    public function getChat(array $params)
    {
        return $this->request('getChat', $this->setParameters($params, 'chat_id'));
    }

    /**
     * Send answers to callback query sent from inline keyboard.
     *
     * @link https://core.telegram.org/bots/api#answerCallbackQuery
     *
     * @param array $params
     *
     * @var string $params ['callback_query_id']
     * @var string $params ['text']
     * @var bool $params ['show_alert']
     * @var string $params ['url']
     * @var int $params ['cache_time']
     *
     * @return array
     */
    public function answerCallbackQuery(array $params)
    {
        return $this->request('answerCallbackQuery', $params);
    }

    /**
     * Edit text message sent by the bot or via the bot (for inline bots).
     *
     * @link https://core.telegram.org/bots/api#editMessageText
     *
     * @param array $params
     *
     * @var int|string $params ['chat_id']
     * @var int $params ['message_id']
     * @var string $params ['inline_message_id']
     * @var string $params ['text']
     * @var string $params ['parse_mode']
     * @var bool $params ['disable_web_page_preview']
     * @var string $params ['reply_markup']
     *
     * @return array
     */
    public function editMessageText(array $params)
    {
        return $this->request('editMessageText', $this->setParameters($params, 'chat_id'));
    }

    /**
     * Edit caption of message sent by the bot or via the bot (for inline bots).
     *
     * @link https://core.telegram.org/bots/api#editMessageCaption
     *
     * @param array $params
     *
     * @var int|string $params ['chat_id']
     * @var int $params ['message_id']
     * @var string $params ['inline_message_id']
     * @var string $params ['caption']
     * @var string $params ['parse_mode']
     * @var string $params ['reply_markup']
     *
     * @return array
     */
    public function editMessageCaption(array $params)
    {
        return $this->request('editMessageCaption', $this->setParameters($params, 'chat_id'));
    }

    /**
     * Edit only the reply markup of message sent by the bot or via the bot (for inline bots).
     *
     * @link https://core.telegram.org/bots/api#editMessageReplyMarkup
     *
     * @param array $params
     *
     * @var int|string $params ['chat_id']
     * @var int $params ['message_id']
     * @var string $params ['inline_message_id']
     * @var string $params ['reply_markup']
     *
     * @return array
     */
    public function editMessageReplyMarkup(array $params)
    {
        return $this->request('editMessageReplyMarkup', $this->setParameters($params, 'chat_id'));
    }

    /**
     * Delete a message, including service messages.
     *
     * @link https://core.telegram.org/bots/api#deleteMessage
     *
     * @param array $params
     *
     * @var int|string $params ['chat_id']
     * @var int $params ['message_id']
     *
     * @return array
     */
    public function deleteMessage(array $params)
    {
        return $this->request('deleteMessage', $this->setParameters($params, 'chat_id'));
    }


    /**
     * Send .webp stickers.
     *
     * @link https://core.telegram.org/bots/api#sendSticker
     *
     * @param array $params
     *
     * @var int|string $params ['chat_id']
     * @var string $params ['sticker']
     * @var bool $params ['disable_notification']
     * @var int $params ['reply_to_message_id']
     * @var string $params ['reply_markup']
     *
     * @return array
     */
    public function sendSticker(array $params)
    {
        return $this->request('sendSticker', $this->setParameters($params, 'chat_id'), true);
    }

    /**
     * Use this method to get a sticker set.
     *
     * @link https://core.telegram.org/bots/api#getStickerSet
     *
     * @param array $params
     *
     * @var string $params ['name']
     *
     * @return array
     */
    public function getStickerSet(array $params)
    {
        return $this->request('getStickerSet', $params);
    }

    /**
     * Use this method to send answer to an inline query.
     *
     * @link https://core.telegram.org/bots/api#answerInlineQuery
     *
     * @param array $params
     *
     * @var string $params ['inline_query_id']
     * @var array $params ['results']
     * @var int|null $params ['cache_time']
     * @var bool|null $params ['is_personal']
     * @var string|null $params ['next_offset']
     * @var string|null $params ['switch_pm_text']
     * @var string|null $params ['switch_pm_parameter']
     *
     * @return array
     */
    public function answerInlineQuery(array $params)
    {
        return $this->request('answerInlineQuery', $params);
    }

    /**
     * Use this method to send invoice.
     *
     * @link https://core.telegram.org/bots/api#sendInvoice
     *
     * @param array $params
     *
     * @var int $params ['chat_id']
     * @var string $params ['title']
     * @var string $params ['description']
     * @var string $params ['payload']
     * @var string $params ['provider_token']
     * @var string $params ['start_parameter']
     * @var string $params ['currency']
     * @var array $params ['prices']
     * @var string $params ['provider_data']
     * @var string $params ['photo_url']
     * @var int $params ['photo_size']
     * @var int $params ['photo_width']
     * @var int $params ['photo_height']
     * @var bool $params ['need_name']
     * @var bool $params ['need_phone_number']
     * @var bool $params ['need_email']
     * @var bool $params ['need_shipping_address']
     * @var bool $params ['send_phone_number_to_provider']
     * @var bool $params ['send_email_to_provider']
     * @var bool $params ['is_flexible']
     * @var bool $params ['disable_notification']
     * @var int $params ['reply_to_message_id']
     * @var string $params ['reply_markup']
     *
     * @return array
     */
    public function sendInvoice(array $params)
    {
        return $this->request('sendInvoice', $this->setParameters($params, 'chat_id'));
    }

    /**
     * Send an Update with a shipping_query field to the bot.
     *
     * @link https://core.telegram.org/bots/api#answerShippingQuery
     *
     * @param array $params
     *
     * @var string $params ['shipping_query_id']
     * @var bool $params ['ok']
     * @var array $params ['shipping_options']
     * @var string $params ['error_message']
     *
     * @return array
     */
    public function answerShippingQuery(array $params)
    {
        return $this->request('answerShippingQuery', $params);
    }

    /**
     * Use this method to get a sticker set.
     *
     * @link https://core.telegram.org/bots/api#answerPreCheckoutQuery
     *
     * @param array $params
     *
     * @var string $params ['pre_checkout_query_id']
     * @var bool $params ['ok']
     * @var string $params ['error_message']
     *
     * @return array
     */
    public function answerPreCheckoutQuery(array $params)
    {
        return $this->request('answerPreCheckoutQuery', $params);
    }

    /**
     * Use this method to get a sticker set.
     *
     * @link https://core.telegram.org/bots/api#sendGame
     *
     * @param array $params
     *
     * @var int $params ['chat_id']
     * @var string $params ['game_short_name']
     * @var bool $params ['disable_notification']
     * @var string $params ['reply_to_message_id']
     * @var string $params ['reply_markup']
     *
     * @return array
     */
    public function sendGame(array $params)
    {
        return $this->request('sendGame', $this->setParameters($params, 'chat_id'));
    }

    /**
     * Use this method to get a sticker set.
     *
     * @link https://core.telegram.org/bots/api#setGameScore
     *
     * @param array $params
     *
     * @var int $params ['user_id']
     * @var int $params ['score']
     * @var bool $params ['force']
     * @var bool $params ['disable_edit_message']
     * @var int $params ['chat_id']
     * @var int $params ['message_id']
     * @var string $params ['inline_message_id']
     *
     * @return array
     */
    public function setGameScore(array $params)
    {
        return $this->request('setGameScore', $this->setParameters($params, 'user_id'));
    }

    /**
     * Use this method to get a sticker set.
     *
     * @link https://core.telegram.org/bots/api#getGameHighScores
     *
     * @param array $params
     *
     * @var int $params ['user_id']
     * @var int $params ['chat_id']
     * @var int $params ['message_id']
     * @var string $params ['inline_message_id']
     *
     * @return array
     */
    public function getGameHighScores(array $params)
    {
        return $this->request('getGameHighScores', $this->setParameters($params, 'user_id'));
    }

    /**
     * Set a Webhook to receive incoming updates via an outgoing webhook.
     *
     * @link https://core.telegram.org/bots/api#setWebhook
     *
     * @param array $params
     *
     * @var string $params ['url']
     * @var string $params ['certificate']
     * @var int $params ['max_connections']
     * @var string|array $params ['allowed_updates']
     *
     * @return array
     */
    public function setWebhook(array $params)
    {
        return $this->request('setWebhook', $params, true);
    }

    /**
     * Delete the outgoing webhook (if any).
     *
     * @link https://core.telegram.org/bots/api#deleteWebhook
     *
     * @return array
     */
    public function deleteWebhook()
    {
        return $this->request('deleteWebhook');
    }

    /**
     * Set parameter array before request.
     *
     * @param array $params
     * @param string|null $targetKey
     *
     * @return array
     */
    protected function setParameters(array $params, $targetKey = null)
    {
        empty($targetKey) ?: $params[$targetKey] = $params[$targetKey] ?? BotAuth::user()->uid;

        return $params;
    }
}