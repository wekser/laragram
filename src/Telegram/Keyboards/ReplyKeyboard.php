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
 * Fluent builder for Telegram ReplyKeyboardMarkup.
 *
 * Usage:
 *
 *   ReplyKeyboard::make()
 *       ->button('Menu')
 *       ->button('Settings')
 *       ->row()
 *       ->button('Help')
 *       ->resize()
 *       ->toArray();
 */
class ReplyKeyboard
{
    /** @var array<int, array<int, array<string, mixed>>>  Rows of buttons. */
    private array $rows = [];

    /** @var array<int, array<string, mixed>>  Buttons in the row currently being built. */
    private array $currentRow = [];

    private bool $resize          = false;
    private bool $oneTime         = false;
    private bool $selective       = false;
    private bool $persistent      = false;
    private ?string $inputPlaceholder = null;

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
     * Add a simple text button.
     */
    public function button(string $text): static
    {
        $this->currentRow[] = ['text' => $text];
        return $this;
    }

    /**
     * Add a button that requests the user's contact.
     */
    public function requestContact(string $text): static
    {
        $this->currentRow[] = ['text' => $text, 'request_contact' => true];
        return $this;
    }

    /**
     * Add a button that requests the user's location.
     */
    public function requestLocation(string $text): static
    {
        $this->currentRow[] = ['text' => $text, 'request_location' => true];
        return $this;
    }

    /**
     * Add a button that requests the user to create a poll.
     * Bot API: KeyboardButtonRequestPoll
     *
     * @param string $type 'regular' or 'quiz'
     */
    public function requestPoll(string $text, string $type = 'regular'): static
    {
        $this->currentRow[] = ['text' => $text, 'request_poll' => ['type' => $type]];
        return $this;
    }

    /**
     * Add a button that requests a user to be shared with the bot.
     * Bot API 6.5+: KeyboardButtonRequestUsers
     */
    public function requestUser(string $text, int $requestId, bool $botRequired = false): static
    {
        $request = ['request_id' => $requestId];

        if ($botRequired) {
            $request['user_is_bot'] = true;
        }

        $this->currentRow[] = ['text' => $text, 'request_users' => $request];
        return $this;
    }

    /**
     * Add a button that requests a chat to be shared with the bot.
     * Bot API 6.5+: KeyboardButtonRequestChat
     */
    public function requestChat(string $text, int $requestId, bool $channelOnly = false): static
    {
        $request = ['request_id' => $requestId];

        if ($channelOnly) {
            $request['chat_is_channel'] = true;
        }

        $this->currentRow[] = ['text' => $text, 'request_chat' => $request];
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
    // Options
    // -------------------------------------------------------------------------

    /** Resize the keyboard to fit the buttons. */
    public function resize(bool $value = true): static
    {
        $this->resize = $value;
        return $this;
    }

    /** Hide the keyboard after the first use. */
    public function oneTime(bool $value = true): static
    {
        $this->oneTime = $value;
        return $this;
    }

    /** Show the keyboard only to mentioned users. */
    public function selective(bool $value = true): static
    {
        $this->selective = $value;
        return $this;
    }

    /** Keep the keyboard visible even when the text input field is minimised. */
    public function persistent(bool $value = true): static
    {
        $this->persistent = $value;
        return $this;
    }

    /** Set a custom placeholder text for the input field. */
    public function placeholder(string $text): static
    {
        $this->inputPlaceholder = $text;
        return $this;
    }

    // -------------------------------------------------------------------------
    // Output
    // -------------------------------------------------------------------------

    /**
     * Return the ReplyKeyboardMarkup array ready for the Telegram API.
     */
    public function toArray(): array
    {
        $rows = $this->rows;

        if (!empty($this->currentRow)) {
            $rows[] = $this->currentRow;
        }

        $markup = ['keyboard' => $rows];

        if ($this->resize)               $markup['resize_keyboard']        = true;
        if ($this->oneTime)              $markup['one_time_keyboard']       = true;
        if ($this->selective)            $markup['selective']               = true;
        if ($this->persistent)           $markup['is_persistent']           = true;
        if ($this->inputPlaceholder)     $markup['input_field_placeholder'] = $this->inputPlaceholder;

        return $markup;
    }

    /**
     * Return a ReplyKeyboardRemove object that hides any custom keyboard.
     *
     * @return array{remove_keyboard: true, selective?: bool}
     */
    public static function remove(bool $selective = false): array
    {
        $result = ['remove_keyboard' => true];

        if ($selective) {
            $result['selective'] = true;
        }

        return $result;
    }
}
