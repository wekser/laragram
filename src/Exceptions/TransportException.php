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
 * A transport-level failure talking to the Telegram Bot API: DNS, TCP, TLS
 * handshake, timeout, dropped connection — anything where no HTTP response
 * body was ever produced.
 *
 * Distinct from an API-level error (Telegram answered with `ok: false`), which
 * is deterministic and maps to a typed exception via TelegramErrorHandler. A
 * transport failure is transient by nature: BotClient already retried it as far
 * as its configuration and idempotency guard allow, so by the time this is
 * thrown the call has definitively failed.
 *
 * Extends ClientResponseInvalidException so existing catch blocks keep working.
 */
class TransportException extends ClientResponseInvalidException
{
    /**
     * @param string $message  Human-readable cURL error
     * @param int    $code     The cURL error number (CURLE_*)
     * @param int    $attempts How many attempts were actually made
     */
    public function __construct(?string $message = null, int $code = 0, public readonly int $attempts = 1)
    {
        parent::__construct($message, $code);
    }
}
