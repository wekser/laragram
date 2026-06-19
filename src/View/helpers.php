<?php

/*
 * This file is part of Laragram.
 *
 * (c) Sergey Lapin <me@wekser.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Wekser\Laragram\Enums\ButtonStyle;
use Wekser\Laragram\View\ComponentContext;
use Wekser\Laragram\View\InlineKeyboardState;
use Wekser\Laragram\View\MediaGroupState;
use Wekser\Laragram\View\ReplyKeyboardState;

// ---------------------------------------------------------------------------
// Inline keyboard helpers — use inside inline_keyboard.php
// ---------------------------------------------------------------------------

// Every inline button helper accepts two optional trailing attributes (Bot API 9.4+):
//   $style — button color: a ButtonStyle case or 'primary' / 'success' / 'danger'
//   $icon  — custom emoji id (icon_custom_emoji_id) shown on the button
// Pass them by name, e.g. button('Delete', 'rm', style: 'danger').

if (!function_exists('button')) {
    /**
     * Add a callback button to the current inline keyboard row.
     *
     * @param string                  $text         Label shown on the button.
     * @param string                  $callbackData Data sent to the bot on press (max 64 bytes).
     * @param ButtonStyle|string|null $style        Optional button color.
     * @param string|null             $icon         Optional custom emoji id.
     */
    function button(string $text, string $callbackData, ButtonStyle|string|null $style = null, ?string $icon = null): void
    {
        $ctx = ComponentContext::current();

        if ($ctx instanceof InlineKeyboardState) {
            $ctx->addButton($text, $callbackData, $style, $icon);
        }
    }
}

if (!function_exists('href')) {
    /**
     * Add a URL button to the current inline keyboard row.
     *
     * @param string                  $text  Label shown on the button.
     * @param string                  $url   URL opened on press.
     * @param ButtonStyle|string|null $style Optional button color.
     * @param string|null             $icon  Optional custom emoji id.
     */
    function href(string $text, string $url, ButtonStyle|string|null $style = null, ?string $icon = null): void
    {
        $ctx = ComponentContext::current();

        if ($ctx instanceof InlineKeyboardState) {
            $ctx->addHref($text, $url, $style, $icon);
        }
    }
}

if (!function_exists('web_app')) {
    /**
     * Add a Telegram Mini App (WebApp) button to the current inline keyboard row.
     *
     * @param string                  $text  Label shown on the button.
     * @param string                  $url   HTTPS URL of the Mini App to open.
     * @param ButtonStyle|string|null $style Optional button color.
     * @param string|null             $icon  Optional custom emoji id.
     */
    function web_app(string $text, string $url, ButtonStyle|string|null $style = null, ?string $icon = null): void
    {
        $ctx = ComponentContext::current();

        if ($ctx instanceof InlineKeyboardState) {
            $ctx->addWebApp($text, $url, $style, $icon);
        }
    }
}

if (!function_exists('login_url')) {
    /**
     * Add a Telegram Login Widget button to the current inline keyboard row.
     *
     * @param string      $text        Label shown on the button.
     * @param string      $url         HTTPS URL to be opened for authorization.
     * @param string|null $forwardText Optional new text of the button in forwarded messages.
     * @param bool        $writeAccess Request permission to send messages to the user.
     */
    function login_url(
        string $text,
        string $url,
        ?string $forwardText = null,
        bool $writeAccess = false,
        ButtonStyle|string|null $style = null,
        ?string $icon = null,
    ): void {
        $ctx = ComponentContext::current();

        if ($ctx instanceof InlineKeyboardState) {
            $ctx->addLoginUrl($text, $url, $forwardText, $writeAccess, $style, $icon);
        }
    }
}

if (!function_exists('switch_inline')) {
    /**
     * Add a button that inserts the bot's username and an inline query into the
     * CURRENT chat's input field (switch_inline_query_current_chat).
     *
     * @param string $text  Label shown on the button.
     * @param string $query Inline query to prefill (empty string inserts just the username).
     */
    function switch_inline(string $text, string $query = '', ButtonStyle|string|null $style = null, ?string $icon = null): void
    {
        $ctx = ComponentContext::current();

        if ($ctx instanceof InlineKeyboardState) {
            $ctx->addSwitchInline($text, $query, $style, $icon);
        }
    }
}

if (!function_exists('switch_inline_chosen')) {
    /**
     * Add a button that prompts the user to select a chat, then inserts the bot's
     * username and an inline query into that chat (switch_inline_query).
     *
     * @param string $text  Label shown on the button.
     * @param string $query Inline query to prefill.
     */
    function switch_inline_chosen(string $text, string $query = '', ButtonStyle|string|null $style = null, ?string $icon = null): void
    {
        $ctx = ComponentContext::current();

        if ($ctx instanceof InlineKeyboardState) {
            $ctx->addSwitchInlineChosen($text, $query, $style, $icon);
        }
    }
}

if (!function_exists('switch_inline_chosen_chat')) {
    /**
     * Add a button that prompts the user to select a chat of the allowed type(s)
     * and inserts an inline query there (switch_inline_query_chosen_chat).
     *
     * @param string $text              Label shown on the button.
     * @param string $query             Inline query to prefill.
     * @param bool   $allowUserChats    Allow private chats with users.
     * @param bool   $allowBotChats     Allow private chats with bots.
     * @param bool   $allowGroupChats   Allow group and supergroup chats.
     * @param bool   $allowChannelChats Allow channel chats.
     */
    function switch_inline_chosen_chat(
        string $text,
        string $query             = '',
        bool   $allowUserChats    = false,
        bool   $allowBotChats     = false,
        bool   $allowGroupChats   = false,
        bool   $allowChannelChats = false,
        ButtonStyle|string|null $style = null,
        ?string $icon              = null,
    ): void {
        $ctx = ComponentContext::current();

        if ($ctx instanceof InlineKeyboardState) {
            $ctx->addSwitchInlineChosenChat($text, $query, $allowUserChats, $allowBotChats, $allowGroupChats, $allowChannelChats, $style, $icon);
        }
    }
}

if (!function_exists('copy_text')) {
    /**
     * Add a button that copies the given text to the clipboard when pressed.
     * Bot API 7.11+
     *
     * @param string $text     Label shown on the button.
     * @param string $copyText Text to be copied (1–256 characters).
     */
    function copy_text(string $text, string $copyText, ButtonStyle|string|null $style = null, ?string $icon = null): void
    {
        $ctx = ComponentContext::current();

        if ($ctx instanceof InlineKeyboardState) {
            $ctx->addCopyText($text, $copyText, $style, $icon);
        }
    }
}

if (!function_exists('pay')) {
    /**
     * Add a Pay button (must be the first button in the first row for invoices).
     *
     * @param string $text Label shown on the button.
     */
    function pay(string $text, ButtonStyle|string|null $style = null, ?string $icon = null): void
    {
        $ctx = ComponentContext::current();

        if ($ctx instanceof InlineKeyboardState) {
            $ctx->addPay($text, $style, $icon);
        }
    }
}

if (!function_exists('callback_game')) {
    /**
     * Add a button that launches a game (must be the first button in the first row).
     *
     * @param string $text Label shown on the button.
     */
    function callback_game(string $text, ButtonStyle|string|null $style = null, ?string $icon = null): void
    {
        $ctx = ComponentContext::current();

        if ($ctx instanceof InlineKeyboardState) {
            $ctx->addCallbackGame($text, $style, $icon);
        }
    }
}

// ---------------------------------------------------------------------------
// Reply keyboard helpers — use inside reply_keyboard.php
// ---------------------------------------------------------------------------

if (!function_exists('reply')) {
    /**
     * Add a button to the current reply keyboard row.
     *
     * @param string                  $text  Label shown on the keyboard button.
     * @param ButtonStyle|string|null $style Optional button color (Bot API 9.4+).
     * @param string|null             $icon  Optional custom emoji id (Bot API 9.4+).
     */
    function reply(string $text, ButtonStyle|string|null $style = null, ?string $icon = null): void
    {
        $ctx = ComponentContext::current();

        if ($ctx instanceof ReplyKeyboardState) {
            $ctx->addButton($text, $style, $icon);
        }
    }
}

if (!function_exists('resize')) {
    /**
     * Request the client to resize the reply keyboard vertically.
     */
    function resize(): void
    {
        $ctx = ComponentContext::current();

        if ($ctx instanceof ReplyKeyboardState) {
            $ctx->setResize();
        }
    }
}

if (!function_exists('one_time')) {
    /**
     * Hide the reply keyboard after the user presses any button.
     */
    function one_time(): void
    {
        $ctx = ComponentContext::current();

        if ($ctx instanceof ReplyKeyboardState) {
            $ctx->setOneTime();
        }
    }
}

// ---------------------------------------------------------------------------
// Shared helper — use inside both keyboard files
// ---------------------------------------------------------------------------

if (!function_exists('row')) {
    /**
     * Start a new row in the current keyboard component.
     */
    function row(): void
    {
        ComponentContext::current()?->addRow();
    }
}

// ---------------------------------------------------------------------------
// Media group helpers — use inside media.php
// ---------------------------------------------------------------------------

if (!function_exists('photo')) {
    /**
     * Add a photo to the media group.
     *
     * @param string      $fileId  Telegram file_id or public URL.
     * @param string|null $caption Optional raw caption (auto-escaped by Laragram).
     */
    function photo(string $fileId, ?string $caption = null): void
    {
        $ctx = ComponentContext::current();

        if ($ctx instanceof MediaGroupState) {
            $ctx->addPhoto($fileId, $caption);
        }
    }
}

if (!function_exists('video')) {
    /**
     * Add a video to the media group.
     *
     * @param string      $fileId  Telegram file_id or public URL.
     * @param string|null $caption Optional raw caption (auto-escaped by Laragram).
     */
    function video(string $fileId, ?string $caption = null): void
    {
        $ctx = ComponentContext::current();

        if ($ctx instanceof MediaGroupState) {
            $ctx->addVideo($fileId, $caption);
        }
    }
}
