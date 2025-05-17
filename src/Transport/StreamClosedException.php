<?php

declare(strict_types=1);

namespace PhpStreamIpc\Transport;

use Exception;
use Throwable;

class StreamClosedException extends Exception
{
    public function __construct(?string $message = null, int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message ?? 'Stream closed', $code, $previous);
    }
}
