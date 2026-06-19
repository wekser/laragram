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
 * Accumulates buttons during the evaluation of a reply_keyboard.php component.
 */
final class ReplyKeyboardState
{
    private array $rows = [];
    private array $currentRow = [];
    private bool $resize = false;
    private bool $oneTime = false;

    public function addButton(string $text, ButtonStyle|string|null $style = null, ?string $icon = null): void
    {
        $this->currentRow[] = ButtonStyle::decorate(['text' => $text], $style, $icon);
    }

    public function addRow(): void
    {
        if (!empty($this->currentRow)) {
            $this->rows[] = $this->currentRow;
            $this->currentRow = [];
        }
    }

    public function setResize(): void
    {
        $this->resize = true;
    }

    public function setOneTime(): void
    {
        $this->oneTime = true;
    }

    public function toArray(): array
    {
        $rows = $this->rows;

        if (!empty($this->currentRow)) {
            $rows[] = $this->currentRow;
        }

        return array_filter([
            'keyboard'          => $rows,
            'resize_keyboard'   => $this->resize ?: null,
            'one_time_keyboard' => $this->oneTime ?: null,
        ], fn($v) => $v !== null);
    }
}
