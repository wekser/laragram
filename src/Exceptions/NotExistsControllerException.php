<?php

namespace Wekser\Laragram\Exceptions;

use Exception;

class NotExistsControllerException extends Exception
{
    public function __construct($controller)
    {
        $this->message = $this->setMessage($controller);
    }

    protected function setMessage($controller)
    {
        return 'The [' . $controller . '] controller not exists';
    }
}