<?php

/*
 * This file is part of Laragram.
 *
 * (c) Sergey Lapin <me@wekser.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Wekser\Laragram\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * This is the BotClient facade class.
 *
 * @method static mixed getMe()
 * @method static mixed sendMessage(array $params)
 * @method static mixed forwardMessage(array $params)
 * @method static mixed sendPhoto(array $params)
 * @method static mixed sendAudio(array $params)
 * @method static mixed sendDocument(array $params)
 * @method static mixed sendVideo(array $params)
 * @method static mixed sendAnimation(array $params)
 * @method static mixed sendVoice(array $params)
 * @method static mixed sendVideoNote(array $params)
 * @method static mixed sendLocation(array $params)
 * @method static mixed editMessageLiveLocation(array $params)
 * @method static mixed stopMessageLiveLocation(array $params)
 * @method static mixed sendVenue(array $params)
 * @method static mixed sendContact(array $params)
 * @method static mixed sendChatAction(array $params)
 * @method static mixed getUserProfilePhotos(array $params)
 * @method static mixed getFile(array $params)
 * @method static mixed getChat(array $params)
 * @method static mixed answerCallbackQuery(array $params)
 * @method static mixed editMessageText(array $params)
 * @method static mixed editMessageCaption(array $params)
 * @method static mixed editMessageReplyMarkup(array $params)
 * @method static mixed deleteMessage(array $params)
 * @method static mixed sendSticker(array $params)
 * @method static mixed getStickerSet(array $params)
 * @method static mixed answerInlineQuery(array $params)
 * @method static mixed sendInvoice(array $params)
 * @method static mixed answerShippingQuery(array $params)
 * @method static mixed answerPreCheckoutQuery(array $params)
 * @method static mixed sendGame(array $params)
 * @method static mixed setGameScore(array $params)
 * @method static mixed getGameHighScores(array $params)
 * @method static mixed sendPoll(array $params)
 * @method static mixed sendDice(array $params)
 * @method static mixed setWebhook(array $params)
 * @method static mixed deleteWebhook()
 */
class BotAPI extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'laragram.api';
    }
}