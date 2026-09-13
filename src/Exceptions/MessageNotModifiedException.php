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

use Wekser\Laragram\Enums\TelegramErrorCode;

/**
 * Exception thrown when an edit would leave the message exactly as it is.
 *
 * Telegram rejects an editMessage* call whose content and reply markup match
 * the current message — typically a double-tapped inline button or a "refresh"
 * that found nothing new. The message is already in the requested state, so
 * this is a benign outcome rather than a failure.
 *
 * @package Wekser\Laragram\Exceptions
 */
class MessageNotModifiedException extends TelegramApiException
{
    public function __construct(string $telegramDescription = '')
    {
        parent::__construct(
            TelegramErrorCode::BAD_REQUEST,
            $telegramDescription ?: 'Bad Request: message is not modified'
        );
    }
}
