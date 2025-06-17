<?php

declare(strict_types=1);

namespace StreamIpc\Message;

use Throwable;

/**
 * Represents an error that occurred during IPC communication.
 *
 * The error message may include information from an optional {@see Throwable}
 * to make troubleshooting easier.
 */
final readonly class ErrorMessage implements Message
{
    private string $errorMessage;

    /**
     * Create a new error message.
     *
     * @param string         $errorMessage User friendly error text.
     * @param Throwable|null $exception    Optional exception to append to the message.
     */
    public function __construct(string $errorMessage, ?Throwable $exception = null)
    {
        if ($exception !== null) {
            $errorMessage .= PHP_EOL . get_class($exception) . ': ' . $exception->getMessage() . PHP_EOL . $exception->getTraceAsString();
        }

        $this->errorMessage = trim($errorMessage);
    }

    /**
     * Render the error as plain text.
     */
    public function toString(): string
    {
        return $this->errorMessage;
    }
}
