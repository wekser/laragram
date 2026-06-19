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

namespace Wekser\Laragram\Telegram\Keyboards;

use Wekser\Laragram\Enums\ButtonStyle;

/**
 * Fluent builder for Telegram InlineKeyboardMarkup.
 *
 * Usage:
 *
 *   InlineKeyboard::make()
 *       ->button('Button 1', callbackData: 'btn_1')
 *       ->href('Link', url: 'https://example.com')
 *       ->row()
 *       ->button('Button 2', callbackData: 'btn_2')
 *       ->toArray();
 *
 * Result:
 *   ['inline_keyboard' => [[...row 1...], [...row 2...], ...]]
 */
class InlineKeyboard
{
    /** @var array<int, array<int, array<string, mixed>>>  Rows of buttons. */
    private array $rows = [];

    /** @var array<int, array<string, mixed>>  Buttons in the row currently being built. */
    private array $currentRow = [];

    private function __construct() {}

    /** Create a new builder instance. */
    public static function make(): static
    {
        return new static();
    }

    // -------------------------------------------------------------------------
    // Button factory methods
    // -------------------------------------------------------------------------

    /**
     * Add a button that fires a callback query.
     *
     * @param ButtonStyle|string|null $style Optional color: 'primary', 'success', 'danger' (Bot API 9.4+).
     * @param string|null             $icon  Optional custom emoji id shown on the button (Bot API 9.4+).
     */
    public function button(string $text, string $callbackData, ButtonStyle|string|null $style = null, ?string $icon = null): static
    {
        return $this->push(['text' => $text, 'callback_data' => $callbackData], $style, $icon);
    }

    /**
     * Add a button that opens a URL.
     */
    public function href(string $text, string $url, ButtonStyle|string|null $style = null, ?string $icon = null): static
    {
        return $this->push(['text' => $text, 'url' => $url], $style, $icon);
    }

    /**
     * Add a button that switches to inline mode in the current chat.
     */
    public function switchInline(string $text, string $query = '', ButtonStyle|string|null $style = null, ?string $icon = null): static
    {
        return $this->push(['text' => $text, 'switch_inline_query_current_chat' => $query], $style, $icon);
    }

    /**
     * Add a button that switches to inline mode in a chosen chat.
     */
    public function switchInlineChosen(string $text, string $query = '', ButtonStyle|string|null $style = null, ?string $icon = null): static
    {
        return $this->push(['text' => $text, 'switch_inline_query' => $query], $style, $icon);
    }

    /**
     * Add a button that prompts the user to select a chat (filtered by type)
     * and inserts the bot's username with an inline query into the input field.
     * Bot API 6.7+: SwitchInlineQueryChosenChat
     */
    public function switchInlineChosenChat(
        string $text,
        string $query             = '',
        bool   $allowUserChats    = false,
        bool   $allowBotChats     = false,
        bool   $allowGroupChats   = false,
        bool   $allowChannelChats = false,
        ButtonStyle|string|null $style = null,
        ?string $icon              = null,
    ): static {
        $chosen = ['query' => $query];

        if ($allowUserChats)    $chosen['allow_user_chats']    = true;
        if ($allowBotChats)     $chosen['allow_bot_chats']     = true;
        if ($allowGroupChats)   $chosen['allow_group_chats']   = true;
        if ($allowChannelChats) $chosen['allow_channel_chats'] = true;

        return $this->push(['text' => $text, 'switch_inline_query_chosen_chat' => $chosen], $style, $icon);
    }

    /**
     * Add a button that opens a Telegram Mini App (WebApp).
     */
    public function webApp(string $text, string $url, ButtonStyle|string|null $style = null, ?string $icon = null): static
    {
        return $this->push(['text' => $text, 'web_app' => ['url' => $url]], $style, $icon);
    }

    /**
     * Add a Pay button (must be the first button in the keyboard for invoices).
     */
    public function pay(string $text, ButtonStyle|string|null $style = null, ?string $icon = null): static
    {
        return $this->push(['text' => $text, 'pay' => true], $style, $icon);
    }

    /**
     * Add a Telegram Login Widget button.
     */
    public function loginUrl(
        string  $text,
        string  $url,
        ?string $forwardText  = null,
        bool    $writeAccess  = false,
        ButtonStyle|string|null $style = null,
        ?string $icon         = null,
    ): static {
        $loginUrl = array_filter([
            'url'                  => $url,
            'forward_text'         => $forwardText,
            'request_write_access' => $writeAccess ?: null,
        ], fn ($v) => $v !== null);

        return $this->push(['text' => $text, 'login_url' => $loginUrl], $style, $icon);
    }

    /**
     * Add a button that copies text to the clipboard when pressed.
     * Bot API 7.11+
     */
    public function copyText(string $text, string $copyText, ButtonStyle|string|null $style = null, ?string $icon = null): static
    {
        return $this->push(['text' => $text, 'copy_text' => ['text' => $copyText]], $style, $icon);
    }

    /**
     * Add a button that launches a game (callback_game).
     * NOTE: must always be the first button in the first row.
     */
    public function callbackGame(string $text, ButtonStyle|string|null $style = null, ?string $icon = null): static
    {
        return $this->push(['text' => $text, 'callback_game' => (object) []], $style, $icon);
    }

    /**
     * Add a raw button array (for advanced cases).
     */
    public function raw(array $button): static
    {
        $this->currentRow[] = $button;
        return $this;
    }

    /**
     * Append a button to the current row, merging optional style / custom-emoji fields.
     */
    private function push(array $button, ButtonStyle|string|null $style = null, ?string $icon = null): static
    {
        $this->currentRow[] = ButtonStyle::decorate($button, $style, $icon);
        return $this;
    }

    // -------------------------------------------------------------------------
    // Pagination helper
    // -------------------------------------------------------------------------

    /**
     * Build a paginated keyboard with item buttons and ← page/total → navigation.
     *
     * Each element of $items may be:
     *   - ['text' => 'Label', 'data' => 'callback_data']
     *   - a plain string (used as both button label and callback data)
     *
     * @param array<int, array{text: string, data: string}|string> $items
     */
    public static function paginate(
        array  $items,
        int    $page,
        int    $perPage        = 5,
        string $callbackPrefix = 'page',
    ): static {
        $keyboard   = static::make();
        $total      = count($items);
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page       = max(1, min($page, $totalPages));
        $pageItems  = array_slice($items, ($page - 1) * $perPage, $perPage);

        foreach ($pageItems as $item) {
            $label = is_array($item) ? $item['text'] : (string) $item;
            $data  = is_array($item) ? ($item['data'] ?? $item['callback_data'] ?? $label) : (string) $item;
            $keyboard->button($label, $data)->row();
        }

        if ($totalPages > 1) {
            if ($page > 1) {
                $keyboard->button('←', "{$callbackPrefix}_" . ($page - 1));
            }
            $keyboard->button("{$page}/{$totalPages}", "{$callbackPrefix}_noop");
            if ($page < $totalPages) {
                $keyboard->button('→', "{$callbackPrefix}_" . ($page + 1));
            }
        }

        return $keyboard;
    }

    // -------------------------------------------------------------------------
    // Layout
    // -------------------------------------------------------------------------

    /**
     * Flush the current row and start a new one.
     */
    public function row(): static
    {
        if (!empty($this->currentRow)) {
            $this->rows[]     = $this->currentRow;
            $this->currentRow = [];
        }

        return $this;
    }

    // -------------------------------------------------------------------------
    // Output
    // -------------------------------------------------------------------------

    /**
     * Return the InlineKeyboardMarkup array ready for the Telegram API.
     */
    public function toArray(): array
    {
        $rows = $this->rows;

        if (!empty($this->currentRow)) {
            $rows[] = $this->currentRow;
        }

        return ['inline_keyboard' => $rows];
    }
}
