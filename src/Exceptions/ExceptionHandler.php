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

namespace Wekser\Laragram\Exceptions;

/**
 * Static exception handler: decides whether to log and renders an empty response.
 *
 * Not an exception itself — acts as a handler/reporter for caught Throwables.
 */
class ExceptionHandler
{
    /**
     * Exception types that are silently swallowed (not logged).
     *
     * @var array<class-string<\Throwable>>
     */
    protected static array $dontReport = [
        AuthenticationException::class,
        UserDeactivatedException::class,
        BotBlockedException::class,
        ChatNotFoundException::class,
    ];

    /**
     * Handle a throwable: log it if reportable.
     *
     * Does NOT send an HTTP response — the caller (Laragram::back()) returns
     * response('OK', 200) when $output is empty, avoiding double-send.
     */
    public static function handle(\Throwable $exception): void
    {
        if (static::shouldReport($exception)) {
            static::report($exception);
        }
    }

    /**
     * Determine whether the exception should be logged.
     */
    public static function shouldReport(\Throwable $exception): bool
    {
        foreach (static::$dontReport as $class) {
            if ($exception instanceof $class) {
                return false;
            }
        }
        return true;
    }

    /**
     * Whether the exception means the user is unreachable (blocked the bot,
     * deactivated, chat gone, or unauthenticated). When one of these is thrown
     * mid-batch, ResponseDispatcher stops sending the remaining messages —
     * there is no point delivering the rest to a user who cannot receive them.
     *
     * These are exactly the $dontReport types: an unreachable user is also a
     * non-reportable condition.
     */
    public static function isTerminal(\Throwable $exception): bool
    {
        return !static::shouldReport($exception);
    }

    /**
     * Log the exception.
     */
    protected static function report(\Throwable $exception): void
    {
        app('log')->error($exception->getMessage(), ['exception' => $exception]);
    }

    /**
     * Build an empty HTTP response so Telegram gets a 200 and stops retrying.
     * Override in a subclass to return a custom response.
     */
    protected static function render(): \Illuminate\Http\Response
    {
        return response('');
    }
}
