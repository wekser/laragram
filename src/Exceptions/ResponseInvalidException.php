<?php
declare(strict_types=1);

namespace Wekser\Laragram\Exceptions;

use Exception;
use Throwable;

class ResponseInvalidException extends Exception
{
    public function __construct(string $uses, int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($this->buildMessage($uses), $code, $previous);
    }

    protected function buildMessage(string $uses): string
    {
        return "Invalid response from [{$uses}].";
    }
}
