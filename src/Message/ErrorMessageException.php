<?php

declare(strict_types=1);

namespace StreamIpc\Message;

use Exception;

class ErrorMessageException extends Exception
{
    public function __construct(ErrorMessage $message)
    {
        parent::__construct($message->toString());
    }
}
