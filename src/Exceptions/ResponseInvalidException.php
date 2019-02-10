<?php

namespace Wekser\Laragram\Exceptions;

use Exception;

class ResponseInvalidException extends Exception
{
    public function __construct($method, $controller)
    {
        $this->message = $this->setMessage($method, $controller);
    }

    protected function setMessage($method, $controller)
    {
        return 'Invalid response from [' . $method . '] method in [' . $controller . '] controller';
    }
}