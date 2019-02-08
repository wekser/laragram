<?php

namespace Wekser\Laragram\Exceptions;

use Exception;

class NotFoundRouteFileException extends Exception
{
    public function __construct($file)
    {
        $this->message = $this->setMessage($file);
    }

    protected function setMessage($file)
    {
        return 'Route file [' . $file . '] not exists';
    }
}