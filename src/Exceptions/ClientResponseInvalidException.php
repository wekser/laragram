<?php

namespace Wekser\Laragram\Exceptions;

use Exception;

class ClientResponseInvalidException extends Exception
{
    protected $message = 'Invalid response from Telegram Bot API though Client.';
}