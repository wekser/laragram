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

namespace Wekser\Laragram\Support;

/**
 * Helpers for normalising bot commands.
 *
 * In group chats Telegram appends the bot's username to a command
 * ("/start@MyBot"). Route patterns are written without it ("/start"), so the
 * mention must be stripped before matching.
 */
final class Command
{
    /**
     * Strip a trailing "@botusername" mention from a single command token.
     *
     * When $botUsername is provided the mention is removed only if it targets
     * this bot (Telegram usernames are case-insensitive), so "/start@OtherBot"
     * is left untouched and never matches. When it is null/empty we fall back to
     * stripping any "@suffix" — a legitimate command can never contain "@", and
     * this keeps commands working out of the box without configuration.
     *
     * A token without "@" (e.g. a private-chat "/start") is returned unchanged.
     */
    public static function stripMention(string $token, ?string $botUsername = null): string
    {
        $at = strpos($token, '@');

        if ($at === false) {
            return $token;
        }

        $command = substr($token, 0, $at);
        $mention = substr($token, $at + 1);

        if ($botUsername === null || $botUsername === '') {
            return $command;
        }

        return strcasecmp($mention, ltrim($botUsername, '@')) === 0 ? $command : $token;
    }
}
