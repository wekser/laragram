<?php
declare(strict_types=1);

namespace Wekser\Laragram\Exceptions;

use Exception;

class RouteEventInvalidException extends Exception
{
    protected $message = 'Invalid route event bind.';
}