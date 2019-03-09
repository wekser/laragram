<?php

namespace Wekser\Laragram\Exceptions;

use Exception;

class RouteActionInvalidException extends Exception
{
    protected $message = 'Invalid route action.';
}