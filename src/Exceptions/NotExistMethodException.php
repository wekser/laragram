<?php

namespace Wekser\Laragram\Exceptions;

use Exception;

class NotExistMethodException extends Exception
{
    public function __construct($method, $controller)
    {
        $this->message = $this->setMessage($method, $controller);
    }

    protected function setMessage($method, $controller)
    {
        return 'The [' . $method . '] method not exists in [' . $controller . '] controller';
    }
}