<?php

declare(strict_types=1);

namespace PhpStreamIpc\Transport;

use RuntimeException;
use Throwable;

/**
 * Exception thrown when an IPC operation (e.g., a request) exceeds its allocated time limit.
 */
class TimeoutException extends RuntimeException
{
    /**
     * Constructs a new TimeoutException.
     *
     * @param string|null $message The exception message. Defaults to 'Request timed out'.
     * @param int $code The exception code.
     * @param Throwable|null $previous The previous throwable used for the exception chaining.
     */
    public function __construct(?string $message = null, int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message ?? 'Request timed out', $code, $previous);
    }
}
