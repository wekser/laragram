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

namespace Wekser\Laragram\View;

use Wekser\Laragram\Enums\ButtonStyle;

/**
 * Accumulates buttons during the evaluation of an inline_keyboard.php component.
 */
final class InlineKeyboardState
{
    private array $rows = [];
    private array $currentRow = [];

    public function addButton(string $text, string $callbackData, ButtonStyle|string|null $style = null, ?string $icon = null): void
    {
        $this->push(['text' => $text, 'callback_data' => $callbackData], $style, $icon);
    }

    public function addHref(string $text, string $url, ButtonStyle|string|null $style = null, ?string $icon = null): void
    {
        $this->push(['text' => $text, 'url' => $url], $style, $icon);
    }

    public function addWebApp(string $text, string $url, ButtonStyle|string|null $style = null, ?string $icon = null): void
    {
        $this->push(['text' => $text, 'web_app' => ['url' => $url]], $style, $icon);
    }

    public function addLoginUrl(
        string  $text,
        string  $url,
        ?string $forwardText = null,
        bool    $writeAccess = false,
        ButtonStyle|string|null $style = null,
        ?string $icon        = null,
    ): void {
        $loginUrl = array_filter([
            'url'                  => $url,
            'forward_text'         => $forwardText,
            'request_write_access' => $writeAccess ?: null,
        ], fn ($v) => $v !== null);

        $this->push(['text' => $text, 'login_url' => $loginUrl], $style, $icon);
    }

    public function addSwitchInline(string $text, string $query = '', ButtonStyle|string|null $style = null, ?string $icon = null): void
    {
        $this->push(['text' => $text, 'switch_inline_query_current_chat' => $query], $style, $icon);
    }

    public function addSwitchInlineChosen(string $text, string $query = '', ButtonStyle|string|null $style = null, ?string $icon = null): void
    {
        $this->push(['text' => $text, 'switch_inline_query' => $query], $style, $icon);
    }

    public function addSwitchInlineChosenChat(
        string $text,
        string $query             = '',
        bool   $allowUserChats    = false,
        bool   $allowBotChats     = false,
        bool   $allowGroupChats   = false,
        bool   $allowChannelChats = false,
        ButtonStyle|string|null $style = null,
        ?string $icon              = null,
    ): void {
        $chosen = ['query' => $query];

        if ($allowUserChats)    $chosen['allow_user_chats']    = true;
        if ($allowBotChats)     $chosen['allow_bot_chats']     = true;
        if ($allowGroupChats)   $chosen['allow_group_chats']   = true;
        if ($allowChannelChats) $chosen['allow_channel_chats'] = true;

        $this->push(['text' => $text, 'switch_inline_query_chosen_chat' => $chosen], $style, $icon);
    }

    public function addCopyText(string $text, string $copyText, ButtonStyle|string|null $style = null, ?string $icon = null): void
    {
        $this->push(['text' => $text, 'copy_text' => ['text' => $copyText]], $style, $icon);
    }

    public function addPay(string $text, ButtonStyle|string|null $style = null, ?string $icon = null): void
    {
        $this->push(['text' => $text, 'pay' => true], $style, $icon);
    }

    public function addCallbackGame(string $text, ButtonStyle|string|null $style = null, ?string $icon = null): void
    {
        $this->push(['text' => $text, 'callback_game' => (object) []], $style, $icon);
    }

    private function push(array $button, ButtonStyle|string|null $style, ?string $icon): void
    {
        $this->currentRow[] = ButtonStyle::decorate($button, $style, $icon);
    }

    public function addRow(): void
    {
        if (!empty($this->currentRow)) {
            $this->rows[] = $this->currentRow;
            $this->currentRow = [];
        }
    }

    public function toArray(): array
    {
        $rows = $this->rows;

        if (!empty($this->currentRow)) {
            $rows[] = $this->currentRow;
        }

        return ['inline_keyboard' => $rows];
    }
}
