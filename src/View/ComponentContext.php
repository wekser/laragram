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
 * Stack-based context for active component builders.
 *
 * Keyboard and media component files call global helper functions
 * (button, row, reply, photo, …) that delegate here, writing into
 * whatever builder was pushed by the renderer before the file was
 * included. Supports nested rendering via a stack.
 */
final class ComponentContext
{
    /** @var array<int, InlineKeyboardState|ReplyKeyboardState|MediaGroupState> */
    private static array $stack = [];

    public static function push(InlineKeyboardState|ReplyKeyboardState|MediaGroupState $state): void
    {
        self::$stack[] = $state;
    }

    public static function pop(): void
    {
        array_pop(self::$stack);
    }

    public static function current(): InlineKeyboardState|ReplyKeyboardState|MediaGroupState|null
    {
        return end(self::$stack) ?: null;
    }

    /** Reset the stack — call in tearDown() when testing view rendering. */
    public static function reset(): void
    {
        self::$stack = [];
    }
}
