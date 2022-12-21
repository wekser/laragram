<?php

namespace Wekser\Laragram\Exceptions;

use Exception;

class ClientResponseInvalidException extends Exception
{
    public function __construct($message = null)
    {
        $this->message = $this->setMessage($message);
    }

    protected function setMessage($message)
    {
        return empty($message) ? 'Invalid response from Telegram Bot API though Client.' : $message;
    }
}