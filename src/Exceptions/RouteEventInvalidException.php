<?php

namespace Wekser\Laragram\Exceptions;

use Exception;

class RouteEventInvalidException extends Exception
{
    protected $message = 'Invalid route event bind.';
}