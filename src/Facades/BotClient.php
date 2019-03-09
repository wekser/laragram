<?php

/*
 * This file is part of Laragram.
 *
 * (c) Sergey Lapin <hello@wekser.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Wekser\Laragram\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * This is the BotClient facade class.
 *
 * @method static array getMe()
 * @method static array sendMessage(array $params)
 * @method static array forwardMessage(array $params)
 * @method static array sendPhoto(array $params)
 * @method static array sendAudio(array $params)
 * @method static array sendDocument(array $params)
 * @method static array sendVideo(array $params)
 * @method static array sendAnimation(array $params)
 * @method static array sendVoice(array $params)
 * @method static array sendVideoNote(array $params)
 * @method static array sendLocation(array $params)
 * @method static array editMessageLiveLocation(array $params)
 * @method static array stopMessageLiveLocation(array $params)
 * @method static array sendVenue(array $params)
 * @method static array sendContact(array $params)
 * @method static array sendChatAction(array $params)
 * @method static array getUserProfilePhotos(array $params)
 * @method static array getFile(array $params)
 * @method static array getChat(array $params)
 * @method static array answerCallbackQuery(array $params)
 * @method static array editMessageText(array $params)
 * @method static array editMessageCaption(array $params)
 * @method static array editMessageReplyMarkup(array $params)
 * @method static array deleteMessage(array $params)
 * @method static array sendSticker(array $params)
 * @method static array getStickerSet(array $params)
 * @method static array answerInlineQuery(array $params)
 * @method static array sendInvoice(array $params)
 * @method static array getToken(array $params)
 * @method static array answerShippingQuery(array $params)
 * @method static array answerPreCheckoutQuery(array $params)
 * @method static array sendGame(array $params)
 * @method static array setGameScore(array $params)
 * @method static array getGameHighScores(array $params)
 * @method static array setWebhook(array $params)
 * @method static array deleteWebhook()
 */
class BotClient extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'laragram.client';
    }
}