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
     */
    public function button(string $text, string $callbackData): static
    {
        $this->currentRow[] = ['text' => $text, 'callback_data' => $callbackData];
        return $this;
    }

    /**
     * Add a button that opens a URL.
     */
    public function href(string $text, string $url): static
    {
        $this->currentRow[] = ['text' => $text, 'url' => $url];
        return $this;
    }

    /**
     * Add a button that switches to inline mode in the current chat.
     */
    public function switchInline(string $text, string $query = ''): static
    {
        $this->currentRow[] = ['text' => $text, 'switch_inline_query_current_chat' => $query];
        return $this;
    }

    /**
     * Add a button that switches to inline mode in a chosen chat.
     */
    public function switchInlineChosen(string $text, string $query = ''): static
    {
        $this->currentRow[] = ['text' => $text, 'switch_inline_query' => $query];
        return $this;
    }

    /**
     * Add a button that opens a Telegram Mini App (WebApp).
     */
    public function webApp(string $text, string $url): static
    {
        $this->currentRow[] = ['text' => $text, 'web_app' => ['url' => $url]];
        return $this;
    }

    /**
     * Add a Pay button (must be the first button in the keyboard for invoices).
     */
    public function pay(string $text): static
    {
        $this->currentRow[] = ['text' => $text, 'pay' => true];
        return $this;
    }

    /**
     * Add a Telegram Login Widget button.
     */
    public function loginUrl(
        string  $text,
        string  $url,
        ?string $forwardText  = null,
        bool    $writeAccess  = false,
    ): static {
        $loginUrl = array_filter([
            'url'                  => $url,
            'forward_text'         => $forwardText,
            'request_write_access' => $writeAccess ?: null,
        ], fn ($v) => $v !== null);

        $this->currentRow[] = ['text' => $text, 'login_url' => $loginUrl];
        return $this;
    }

    /**
     * Add a button that copies text to the clipboard when pressed.
     * Bot API 7.11+
     */
    public function copyText(string $text, string $copyText): static
    {
        $this->currentRow[] = ['text' => $text, 'copy_text' => ['text' => $copyText]];
        return $this;
    }

    /**
     * Add a raw button array (for advanced cases).
     */
    public function raw(array $button): static
    {
        $this->currentRow[] = $button;
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
