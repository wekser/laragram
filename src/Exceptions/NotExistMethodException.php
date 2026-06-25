<?php
declare(strict_types=1);

namespace Wekser\Laragram\Exceptions;

use Exception;
use Throwable;

class NotExistMethodException extends Exception
{
    public function __construct(string $method, string $controller, int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($this->buildMessage($method, $controller), $code, $previous);
    }

    protected function buildMessage(string $method, string $controller): string
    {
        return "The [{$method}] method not exists in [{$controller}] controller.";
    }
}
