<?php
declare(strict_types=1);

namespace Wekser\Laragram\Exceptions;

use Exception;
use Throwable;

class FileInvalidException extends Exception
{
    public function __construct(string $path, int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($this->buildMessage($path), $code, $previous);
    }

    protected function buildMessage(string $path): string
    {
        return "Failed to create file entity. Unable to read resource: {$path}.";
    }
}
