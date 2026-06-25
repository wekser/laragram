<?php
declare(strict_types=1);

namespace Wekser\Laragram\Exceptions;

use Exception;
use Throwable;

class NotFoundRouteFileException extends Exception
{
    public function __construct(string $file, int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($this->buildMessage($file), $code, $previous);
    }

    protected function buildMessage(string $file): string
    {
        return "Route file [{$file}] not exists.";
    }
}
