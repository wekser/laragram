<?php
declare(strict_types=1);

namespace Wekser\Laragram\Exceptions;

use Exception;
use Throwable;

class NotExistsControllerException extends Exception
{
    public function __construct(string $controller, int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($this->buildMessage($controller), $code, $previous);
    }

    protected function buildMessage(string $controller): string
    {
        return "The [{$controller}] controller not exists.";
    }
}
