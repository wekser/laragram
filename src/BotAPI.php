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

namespace Wekser\Laragram;

/**
 * Thin proxy to the Telegram Bot API.
 *
 * Every public call is forwarded to BotClient::request($method, $params),
 * so any new Telegram method works automatically without changes here.
 *
 * --- Bot info ---
 * @method mixed getMe()
 * @method mixed logOut()
 * @method mixed close()
 *
 * --- Messages ---
 * @method mixed sendMessage(array $params)
 * @method mixed forwardMessage(array $params)
 * @method mixed copyMessage(array $params)
 * @method mixed deleteMessage(array $params)
 * @method mixed deleteMessages(array $params)
 * @method mixed pinChatMessage(array $params)
 * @method mixed unpinChatMessage(array $params)
 * @method mixed unpinAllChatMessages(array $params)
 *
 * --- Editing ---
 * @method mixed editMessageText(array $params)
 * @method mixed editMessageCaption(array $params)
 * @method mixed editMessageMedia(array $params)
 * @method mixed editMessageReplyMarkup(array $params)
 * @method mixed editMessageLiveLocation(array $params)
 * @method mixed stopMessageLiveLocation(array $params)
 *
 * --- Media ---
 * @method mixed sendPhoto(array $params)
 * @method mixed sendAudio(array $params)
 * @method mixed sendDocument(array $params)
 * @method mixed sendVideo(array $params)
 * @method mixed sendAnimation(array $params)
 * @method mixed sendVoice(array $params)
 * @method mixed sendVideoNote(array $params)
 * @method mixed sendMediaGroup(array $params)
 * @method mixed sendSticker(array $params)
 * @method mixed sendDice(array $params)
 *
 * --- Location & Contact ---
 * @method mixed sendLocation(array $params)
 * @method mixed sendVenue(array $params)
 * @method mixed sendContact(array $params)
 *
 * --- Chat actions & info ---
 * @method mixed sendChatAction(array $params)
 * @method mixed getChat(array $params)
 * @method mixed getChatMember(array $params)
 * @method mixed getChatMemberCount(array $params)
 * @method mixed getChatAdministrators(array $params)
 *
 * --- Chat administration ---
 * @method mixed banChatMember(array $params)
 * @method mixed unbanChatMember(array $params)
 * @method mixed restrictChatMember(array $params)
 * @method mixed promoteChatMember(array $params)
 * @method mixed setChatAdministratorCustomTitle(array $params)
 * @method mixed leaveChat(array $params)
 *
 * --- Invite links ---
 * @method mixed exportChatInviteLink(array $params)
 * @method mixed createChatInviteLink(array $params)
 * @method mixed editChatInviteLink(array $params)
 * @method mixed revokeChatInviteLink(array $params)
 * @method mixed approveChatJoinRequest(array $params)
 * @method mixed declineChatJoinRequest(array $params)
 *
 * --- User ---
 * @method mixed getUserProfilePhotos(array $params)
 * @method mixed getFile(array $params)
 *
 * --- Polls ---
 * @method mixed sendPoll(array $params)
 * @method mixed stopPoll(array $params)
 *
 * --- Callbacks & Inline ---
 * @method mixed answerCallbackQuery(array $params)
 * @method mixed answerInlineQuery(array $params)
 *
 * --- Stickers ---
 * @method mixed getStickerSet(array $params)
 * @method mixed getCustomEmojiStickers(array $params)
 * @method mixed uploadStickerFile(array $params)
 * @method mixed createNewStickerSet(array $params)
 * @method mixed addStickerToSet(array $params)
 * @method mixed deleteStickerFromSet(array $params)
 *
 * --- Games ---
 * @method mixed sendGame(array $params)
 * @method mixed setGameScore(array $params)
 * @method mixed getGameHighScores(array $params)
 *
 * --- Payments ---
 * @method mixed sendInvoice(array $params)
 * @method mixed createInvoiceLink(array $params)
 * @method mixed answerShippingQuery(array $params)
 * @method mixed answerPreCheckoutQuery(array $params)
 * @method mixed refundStarPayment(array $params)
 * @method mixed getStarTransactions(array $params)
 *
 * --- Webhook ---
 * @method mixed setWebhook(array $params)
 * @method mixed deleteWebhook()
 * @method mixed getWebhookInfo()
 *
 * @package Wekser\Laragram
 */
class BotAPI
{
    /**
     * @var BotClient
     */
    protected BotClient $client;

    /**
     * @param string $token
     */
    public function __construct(string $token)
    {
        $this->client = new BotClient($token);
    }

    /**
     * Return the underlying BotClient instance.
     * Useful for configuring timeouts (e.g. long polling).
     */
    public function getClient(): BotClient
    {
        return $this->client;
    }

    /**
     * Forward any API method call to BotClient.
     *
     * @param string $method  Telegram Bot API method name
     * @param array  $arguments
     * @return mixed
     */
    public function __call(string $method, array $arguments): mixed
    {
        $params = $arguments[0] ?? [];

        return $this->client->request($method, is_array($params) ? $params : []);
    }
}
