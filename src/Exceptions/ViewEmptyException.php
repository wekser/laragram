<?php
declare(strict_types=1);

namespace Wekser\Laragram\Exceptions;

use Exception;
use Throwable;

class ViewEmptyException extends Exception
{
    public function __construct(string $view, int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($this->buildMessage($view), $code, $previous);
    }

    protected function buildMessage(string $view): string
    {
        return "The [{$view}] view is empty.";
    }
}
