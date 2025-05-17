<?php

declare(strict_types=1);

namespace PhpStreamIpc\Transport;

use Exception;
use Throwable;

/**
 * Exception thrown when an operation is attempted on a stream that has been closed.
 */
class StreamClosedException extends Exception
{
    /**
     * Constructs a new StreamClosedException.
     *
     * @param string|null $message The exception message. Defaults to 'Stream closed'.
     * @param int $code The exception code.
     * @param Throwable|null $previous The previous throwable used for the exception chaining.
     */
    public function __construct(?string $message = null, int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message ?? 'Stream closed', $code, $previous);
    }
}
