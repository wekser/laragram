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

/**
 * Accumulates buttons during the evaluation of an inline_keyboard.php component.
 */
final class InlineKeyboardState
{
    private array $rows = [];
    private array $currentRow = [];

    public function addButton(string $text, string $callbackData): void
    {
        $this->currentRow[] = ['text' => $text, 'callback_data' => $callbackData];
    }

    public function addHref(string $text, string $url): void
    {
        $this->currentRow[] = ['text' => $text, 'url' => $url];
    }

    public function addWebApp(string $text, string $url): void
    {
        $this->currentRow[] = ['text' => $text, 'web_app' => ['url' => $url]];
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
