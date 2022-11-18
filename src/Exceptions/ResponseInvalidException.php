<?php

namespace Wekser\Laragram\Exceptions;

use Exception;

class ResponseInvalidException extends Exception
{
    public function __construct($uses)
    {
        $this->message = $this->setMessage($uses);
    }

    protected function setMessage($uses)
    {
        return 'Invalid response from [' . $uses . '].';
    }
}