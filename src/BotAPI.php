<?php

/*
 * This file is part of Laragram.
 *
 * (c) Sergey Lapin <me@wekser.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Wekser\Laragram;

use Wekser\Laragram\Facades\BotAuth;

class BotAPI
{
    /**
     * BotAPI Class.
     *
     * @var BotClient
     */
    protected BotClient $client;

    /**
     * BotAPI Constructor.
     *
     * @param string $token
     */
    public function __construct(string $token)
    {
        $this->client = new BotClient($token);
    }

    /**
     * A simple method for testing your bot's auth token.
     *
     * @link https://core.telegram.org/bots/api#getMe
     *
     * @return array
     */
    public function getMe()
    {
        return $this->client->request('getMe');
    }

    /**
     * Use this method to send text messages.
     *
     * @link https://core.telegram.org/bots/api#sendMessage
     *
     * @param array $params
     * @return array
     */
    public function sendMessage(array $params)
    {
        return $this->client->request('sendMessage', $params);
    }

    /**
     * Use this method to forward messages of any kind.
     *
     * @link https://core.telegram.org/bots/api#forwardMessage
     *
     * @param array $params
     * @return array
     */
    public function forwardMessage(array $params)
    {
        return $this->client->request('forwardMessage', $params);
    }

    /**
     * Use this method to send photos.
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
     * @return mixed
     */
    public function sendPhoto(array $params)
    {
        return $this->client->request('sendPhoto', $params);
    }

    /**
     * Use this method to send audio files, if you want Telegram clients to display them in the music player. Your audio must be in the .MP3 or .M4A format.
     *
     * @link https://core.telegram.org/bots/api#sendAudio
     *
     * @param array $params
     * @return array
     */
    public function sendAudio(array $params)
    {
        return $this->client->request('sendAudio', $params);
    }

    /**
     * Use this method to send general files.
     *
     * @link https://core.telegram.org/bots/api#sendDocument
     *
     * @param array $params
     * @return array
     */
    public function sendDocument(array $params)
    {
        return $this->client->request('sendDocument', $params);
    }

    /**
     * Use this method to send video files, Telegram clients support MPEG4 videos (other formats may be sent as Document).
     *
     * @link https://core.telegram.org/bots/api#sendVideo
     *
     * @param array $params
     * @return array
     */
    public function sendVideo(array $params)
    {
        return $this->client->request('sendVideo', $params);
    }

    /**
     * Use this method to send a group of photos, videos, documents or audios as an album. Documents and audio files can be only grouped in an album with messages of the same type.
     *
     * @link https://core.telegram.org/bots/api#sendMediaGroup
     *
     * @param array $params
     * @return array
     */
    public function sendMediaGroup(array $params)
    {
        return $this->client->request('sendMediaGroup', $params);
    }

    /**
     * Use this method to send animation files (GIF or H.264/MPEG-4 AVC video without sound).
     *
     * @link https://core.telegram.org/bots/api#sendAnimation
     *
     * @param array $params
     * @return array
     */
    public function sendAnimation(array $params)
    {
        return $this->client->request('sendAnimation', $params);
    }

    /**
     * Use this method to send audio files, if you want Telegram clients to display the file as a playable voice message. For this to work, your audio must be in an .OGG file encoded with OPUS (other formats may be sent as Audio or Document).
     *
     * @link https://core.telegram.org/bots/api#sendVoice
     *
     * @param array $params
     * @return array
     */
    public function sendVoice(array $params)
    {
        return $this->client->request('sendVoice', $params);
    }

    /**
     * As of v.4.0, Telegram clients support rounded square MPEG4 videos of up to 1 minute long. Use this method to send video messages.
     *
     * @link https://core.telegram.org/bots/api#sendVideoNote
     *
     * @param array $params
     * @return array
     */
    public function sendVideoNote(array $params)
    {
        return $this->client->request('sendVideoNote', $params);
    }

    /**
     * Use this method to send point on the map.
     *
     * @link https://core.telegram.org/bots/api#sendLocation
     *
     * @param array $params
     * @return array
     */
    public function sendLocation(array $params)
    {
        return $this->client->request('sendLocation', $params);
    }

    /**
     * Use this method to edit live location messages. A location can be edited until its live_period expires or editing is explicitly disabled by a call to stopMessageLiveLocation.
     *
     * @link https://core.telegram.org/bots/api#editMessageLiveLocation
     *
     * @param array $params
     * @return array
     */
    public function editMessageLiveLocation(array $params)
    {
        return $this->client->request('editMessageLiveLocation', $params);
    }

    /**
     * Use this method to stop updating a live location message before live_period expires.
     *
     * @link https://core.telegram.org/bots/api#stopMessageLiveLocation
     *
     * @param array $params
     * @return array
     */
    public function stopMessageLiveLocation(array $params)
    {
        return $this->client->request('stopMessageLiveLocation', $params);
    }

    /**
     * Use this method to send information about a venue.
     *
     * @link https://core.telegram.org/bots/api#sendVenue
     *
     * @param array $params
     * @return array
     */
    public function sendVenue(array $params)
    {
        return $this->client->request('sendVenue', $params);
    }

    /**
     * Use this method to send phone contacts.
     *
     * @link https://core.telegram.org/bots/api#sendContact
     *
     * @param array $params
     * @return array
     */
    public function sendContact(array $params)
    {
        return $this->client->request('sendContact', $params);
    }

    /**
     * Use this method when you need to tell the user that something is happening on the bot's side. The status is set for 5 seconds or less (when a message arrives from your bot, Telegram clients clear its typing status).
     *
     * @link https://core.telegram.org/bots/api#sendChatAction
     *
     * @param array $params
     * @return array
     */
    public function sendChatAction(array $params)
    {
        return $this->client->request('sendChatAction', $params);
    }

    /**
     * Use this method to get a list of profile pictures for a user.
     *
     * @link https://core.telegram.org/bots/api#getUserProfilePhotos
     *
     * @param array $params
     * @return array
     */
    public function getUserProfilePhotos(array $params)
    {
        return $this->client->request('getUserProfilePhotos', $params);
    }

    /**
     * Use this method to get basic information about a file and prepare it for downloading.
     *
     * @link https://core.telegram.org/bots/api#getFile
     *
     * @param array $params
     * @return array
     */
    public function getFile(array $params)
    {
        return $this->client->request('getFile', $params);
    }

    /**
     * Use this method to get up to date information about the chat (current name of the user for one-on-one conversations, current username of a user, group or channel, etc.).
     *
     * @link https://core.telegram.org/bots/api#getChat
     *
     * @param array $params
     * @return array
     */
    public function getChat(array $params)
    {
        return $this->client->request('getChat', $params);
    }

    /**
     * Use this method to send answers to callback queries sent from inline keyboards. The answer will be displayed to the user as a notification at the top of the chat screen or as an alert.
     *
     * @link https://core.telegram.org/bots/api#answerCallbackQuery
     *
     * @param array $params
     * @return array
     */
    public function answerCallbackQuery(array $params)
    {
        return $this->client->request('answerCallbackQuery', $params);
    }

    /**
     * Use this method to edit text and game messages.
     *
     * @link https://core.telegram.org/bots/api#editMessageText
     *
     * @param array $params
     * @return array
     */
    public function editMessageText(array $params)
    {
        return $this->client->request('editMessageText', $params);
    }

    /**
     * Use this method to edit captions of messages.
     *
     * @link https://core.telegram.org/bots/api#editMessageCaption
     *
     * @param array $params
     * @return array
     */
    public function editMessageCaption(array $params)
    {
        return $this->client->request('editMessageCaption', $params);
    }

    /**
     * Use this method to edit animation, audio, document, photo, or video messages.
     *
     * @link https://core.telegram.org/bots/api#editMessageMedia
     *
     * @param array $params
     * @return array
     */
    public function editMessageMedia(array $params)
    {
        return $this->client->request('editMessageMedia', $params);
    }

    /**
     * Use this method to edit only the reply markup of messages.
     *
     * @link https://core.telegram.org/bots/api#editMessageReplyMarkup
     *
     * @param array $params
     * @return array
     */
    public function editMessageReplyMarkup(array $params)
    {
        return $this->client->request('editMessageReplyMarkup', $params);
    }

    /**
     * Delete a message, including service messages.
     *
     * @link https://core.telegram.org/bots/api#deleteMessage
     *
     * @param array $params
     * @return array
     */
    public function deleteMessage(array $params)
    {
        return $this->client->request('deleteMessage', $params);
    }

    /**
     * Use this method to send static .WEBP, animated .TGS, or video .WEBM stickers.
     *
     * @link https://core.telegram.org/bots/api#sendSticker
     *
     * @param array $params
     * @return array
     */
    public function sendSticker(array $params)
    {
        return $this->client->request('sendSticker', $params);
    }

    /**
     * Use this method to get a sticker set.
     *
     * @link https://core.telegram.org/bots/api#getStickerSet
     *
     * @param array $params
     * @return array
     */
    public function getStickerSet(array $params)
    {
        return $this->client->request('getStickerSet', $params);
    }

    /**
     * Use this method to send answer to an inline query.
     *
     * @link https://core.telegram.org/bots/api#answerInlineQuery
     *
     * @param array $params
     * @return array
     */
    public function answerInlineQuery(array $params)
    {
        return $this->client->request('answerInlineQuery', $params);
    }

    /**
     * Use this method to send invoice.
     *
     * @link https://core.telegram.org/bots/api#sendInvoice
     *
     * @param array $params
     * @return array
     */
    public function sendInvoice(array $params)
    {
        return $this->client->request('sendInvoice', $params);
    }

    /**
     * If you sent an invoice requesting a shipping address and the parameter is_flexible was specified, the Bot API will send an Update with a shipping_query field to the bot. Use this method to reply to shipping queries.
     *
     * @link https://core.telegram.org/bots/api#answerShippingQuery
     *
     * @param array $params
     * @return array
     */
    public function answerShippingQuery(array $params)
    {
        return $this->client->request('answerShippingQuery', $params);
    }

    /**
     * Use this method to get a sticker set.
     *
     * @link https://core.telegram.org/bots/api#answerPreCheckoutQuery
     *
     * @param array $params
     * @return array
     */
    public function answerPreCheckoutQuery(array $params)
    {
        return $this->client->request('answerPreCheckoutQuery', $params);
    }

    /**
     * Use this method to get a sticker set.
     *
     * @link https://core.telegram.org/bots/api#sendGame
     *
     * @param array $params
     * @return array
     */
    public function sendGame(array $params)
    {
        return $this->client->request('sendGame', $params);
    }

    /**
     * Use this method to get a sticker set.
     *
     * @link https://core.telegram.org/bots/api#setGameScore
     *
     * @param array $params
     * @return array
     */
    public function setGameScore(array $params)
    {
        return $this->client->request('setGameScore', $params);
    }

    /**
     * Use this method to get a sticker set.
     *
     * @link https://core.telegram.org/bots/api#getGameHighScores
     *
     * @param array $params
     * @return array
     */
    public function getGameHighScores(array $params)
    {
        return $this->client->request('getGameHighScores', $params);
    }

    /**
     * Use this method to send a native poll.
     *
     * @link https://core.telegram.org/bots/api#sendPoll
     *
     * @param array $params
     * @return array
     */
    public function sendPoll(array $params)
    {
        return $this->client->request('sendPoll', $params);
    }

    /**
     * Use this method to send an animated emoji that will display a random value.
     *
     * @link https://core.telegram.org/bots/api#sendDice
     *
     * @param array $params
     * @return array
     */
    public function sendDice(array $params)
    {
        return $this->client->request('sendDice', $params);
    }

    /**
     * Use this method to specify a URL and receive incoming updates via an outgoing webhook.
     *
     * @link https://core.telegram.org/bots/api#setWebhook
     *
     * @param array $params
     * @return array
     */
    public function setWebhook(array $params)
    {
        return $this->client->request('setWebhook', $params);
    }

    /**
     * Use this method to remove webhook integration if you decide to switch back to getUpdates.
     *
     * @link https://core.telegram.org/bots/api#deleteWebhook
     *
     * @return array
     */
    public function deleteWebhook()
    {
        return $this->client->request('deleteWebhook');
    }
}
