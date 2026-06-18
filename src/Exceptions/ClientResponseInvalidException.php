<?php

namespace Wekser\Laragram\Exceptions;

use Exception;

class ClientResponseInvalidException extends Exception
{
    public function __construct(?string $message = null, int $code = 0)
    {
        parent::__construct($this->setMessage($message), $code);
    }

    protected function setMessage(?string $message): string
    {
        return empty($message) ? 'Invalid response from Telegram Bot API through Client.' : $message;
    }
}