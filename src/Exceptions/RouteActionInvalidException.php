<?php
declare(strict_types=1);

namespace Wekser\Laragram\Exceptions;

use Exception;

class RouteActionInvalidException extends Exception
{
    protected $message = 'Invalid route action.';
}