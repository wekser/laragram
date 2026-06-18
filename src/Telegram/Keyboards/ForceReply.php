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
 * Fluent builder for Telegram ForceReply.
 *
 * Forces a reply interface on the client side — useful for guided multi-step forms.
 *
 * Usage:
 *
 *   ForceReply::make()->toArray();
 *
 *   ForceReply::make()
 *       ->placeholder('Enter your name…')
 *       ->selective()
 *       ->toArray();
 *
 * Result: ['force_reply' => true, ...]
 */
class ForceReply
{
    private bool    $selective        = false;
    private ?string $inputPlaceholder = null;

    private function __construct() {}

    /** Create a new builder instance. */
    public static function make(): static
    {
        return new static();
    }

    /**
     * Show the reply interface only to mentioned users (and the sender of the
     * original message in reply mode).
     */
    public function selective(bool $value = true): static
    {
        $this->selective = $value;
        return $this;
    }

    /** Set a custom placeholder text shown in the input field. */
    public function placeholder(string $text): static
    {
        $this->inputPlaceholder = $text;
        return $this;
    }

    /**
     * Return the ForceReply object ready for the Telegram API.
     *
     * @return array{force_reply: true, selective?: bool, input_field_placeholder?: string}
     */
    public function toArray(): array
    {
        $result = ['force_reply' => true];

        if ($this->selective) {
            $result['selective'] = true;
        }

        if ($this->inputPlaceholder !== null) {
            $result['input_field_placeholder'] = $this->inputPlaceholder;
        }

        return $result;
    }
}
