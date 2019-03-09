<?php

namespace Wekser\Laragram\Exceptions;

use Exception;

class FileInvalidException extends Exception
{
    public function __construct($path)
    {
        $this->message = $this->setMessage($path);
    }

    protected function setMessage($path)
    {
        return 'Failed to create file entity. Unable to read resource: '.$path.'.';
    }
}