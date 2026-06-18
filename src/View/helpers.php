<?php

/*
 * This file is part of Laragram.
 *
 * (c) Sergey Lapin <me@wekser.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Wekser\Laragram\View\ComponentContext;
use Wekser\Laragram\View\InlineKeyboardState;
use Wekser\Laragram\View\MediaGroupState;
use Wekser\Laragram\View\ReplyKeyboardState;

// ---------------------------------------------------------------------------
// Inline keyboard helpers — use inside inline_keyboard.php
// ---------------------------------------------------------------------------

if (!function_exists('button')) {
    /**
     * Add a callback button to the current inline keyboard row.
     *
     * @param string $text         Label shown on the button.
     * @param string $callbackData Data sent to the bot on press (max 64 bytes).
     */
    function button(string $text, string $callbackData): void
    {
        $ctx = ComponentContext::current();

        if ($ctx instanceof InlineKeyboardState) {
            $ctx->addButton($text, $callbackData);
        }
    }
}

if (!function_exists('href')) {
    /**
     * Add a URL button to the current inline keyboard row.
     *
     * @param string $text Label shown on the button.
     * @param string $url  URL opened on press.
     */
    function href(string $text, string $url): void
    {
        $ctx = ComponentContext::current();

        if ($ctx instanceof InlineKeyboardState) {
            $ctx->addHref($text, $url);
        }
    }
}

if (!function_exists('web_app')) {
    /**
     * Add a Telegram Mini App (WebApp) button to the current inline keyboard row.
     *
     * @param string $text Label shown on the button.
     * @param string $url  HTTPS URL of the Mini App to open.
     */
    function web_app(string $text, string $url): void
    {
        $ctx = ComponentContext::current();

        if ($ctx instanceof InlineKeyboardState) {
            $ctx->addWebApp($text, $url);
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
     * @param string $text Label shown on the keyboard button.
     */
    function reply(string $text): void
    {
        $ctx = ComponentContext::current();

        if ($ctx instanceof ReplyKeyboardState) {
            $ctx->addButton($text);
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
