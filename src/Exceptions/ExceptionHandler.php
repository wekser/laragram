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

use Wekser\Laragram\Events\BotExceptionHandled;

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
     * Exception types that are benign outcomes rather than failures: not
     * logged, and — unlike $dontReport — not terminal, so the rest of a
     * batch is still delivered and the user is never deactivated.
     *
     * @var array<class-string<\Throwable>>
     */
    protected static array $ignore = [
        MessageNotModifiedException::class,
    ];

    /**
     * Handle a throwable: log it if reportable.
     *
     * Does NOT send an HTTP response — the caller (Laragram::back()) returns
     * response('OK', 200) when $output is empty, avoiding double-send.
     */
    public static function handle(\Throwable $exception): void
    {
        $reportable = static::shouldReport($exception);

        if ($reportable) {
            static::report($exception);
        }

        static::notify($exception, $reportable);
    }

    /**
     * Emit the observability event for a handled throwable.
     *
     * This is the single seam where every swallowed failure — routing, delivery
     * and the queued job — becomes observable, including the user-unreachable
     * conditions that are otherwise silenced. It must never break the swallow
     * contract: a faulty listener cannot be allowed to re-throw out of handle(),
     * so dispatch is best-effort and guarded.
     */
    protected static function notify(\Throwable $exception, bool $reportable): void
    {
        try {
            event(new BotExceptionHandled($exception, $reportable, static::isTerminal($exception)));
        } catch (\Throwable) {
            // An observability hook must never turn a handled error into a fatal one.
        }
    }

    /**
     * Determine whether the exception should be logged.
     */
    public static function shouldReport(\Throwable $exception): bool
    {
        return !static::isTerminal($exception) && !static::isIgnorable($exception);
    }

    /**
     * Whether the exception is a benign outcome ($ignore) — e.g. an edit that
     * would leave the message unchanged. Silenced from the log, but the user
     * is reachable, so it is neither terminal nor a reason to stop a batch.
     */
    public static function isIgnorable(\Throwable $exception): bool
    {
        return static::matchesAny($exception, static::$ignore);
    }

    /**
     * Whether the exception means the user is unreachable (blocked the bot,
     * deactivated, chat gone, or unauthenticated). When one of these is thrown
     * mid-batch, ResponseDispatcher stops sending the remaining messages —
     * there is no point delivering the rest to a user who cannot receive them.
     *
     * These are exactly the $dontReport types: an unreachable user is also a
     * non-reportable condition. The $ignore types are silenced too, but are
     * deliberately not terminal.
     */
    public static function isTerminal(\Throwable $exception): bool
    {
        return static::matchesAny($exception, static::$dontReport);
    }

    /**
     * @param array<class-string<\Throwable>> $classes
     */
    protected static function matchesAny(\Throwable $exception, array $classes): bool
    {
        foreach ($classes as $class) {
            if ($exception instanceof $class) {
                return true;
            }
        }
        return false;
    }

    /**
     * Whether the exception is a transport-level failure — the call never
     * reached Telegram (DNS, connect, TLS handshake, timeout, dropped
     * connection). BotClient has already retried it as far as its config and
     * idempotency guard allow, so the network is, right now, unusable.
     *
     * ResponseDispatcher stops the batch on one of these: every remaining
     * message would burn its own connect timeout on the same dead path, and a
     * webhook that keeps the HTTP worker busy long enough gets the update
     * redelivered by Telegram.
     */
    public static function isTransport(\Throwable $exception): bool
    {
        return $exception instanceof TransportException;
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
