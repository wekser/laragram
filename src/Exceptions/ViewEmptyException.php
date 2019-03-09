<?php

namespace Wekser\Laragram\Exceptions;

use Exception;

class ViewEmptyException extends Exception
{
    public function __construct($view)
    {
        $this->message = $this->setMessage($view);
    }

    protected function setMessage($view)
    {
        return 'The [' . $view . '] view is empty.';
    }
}