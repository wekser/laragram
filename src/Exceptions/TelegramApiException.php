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
 * Base exception for Telegram API errors
 * 
 * @package Wekser\Laragram\Exceptions
 */
class TelegramApiException extends \RuntimeException
{
    protected TelegramErrorCode $errorCode;
    protected string $telegramDescription;
    protected array $parameters;

    public function __construct(
        TelegramErrorCode $errorCode,
        string $telegramDescription = '',
        array $parameters = [],
        int $code = 0,
        ?\Exception $previous = null
    ) {
        $this->errorCode = $errorCode;
        $this->telegramDescription = $telegramDescription;
        $this->parameters = $parameters;

        $message = $errorCode->getDetailedDescription($telegramDescription);
        
        parent::__construct($message, $code, $previous);
    }

    public function getErrorCode(): TelegramErrorCode
    {
        return $this->errorCode;
    }

    public function getTelegramDescription(): string
    {
        return $this->telegramDescription;
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function getRecommendedAction(): string
    {
        return $this->errorCode->getRecommendedAction();
    }

    public function requiresUserDeactivation(): bool
    {
        return $this->errorCode->requiresUserDeactivation();
    }

    public function requiresSpecialHandling(): bool
    {
        return $this->errorCode->requiresSpecialHandling();
    }
}

