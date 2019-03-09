<?php

namespace Wekser\Laragram\Exceptions;

use Exception;

class TokenInvalidException extends Exception
{
    protected $message = 'The bot token is not specified in the configuration file.';
}