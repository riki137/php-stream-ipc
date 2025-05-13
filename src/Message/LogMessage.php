<?php

declare(strict_types=1);

namespace PhpStreamIpc\Message;

use PhpStreamIpc\Message\Message;

/**
 * Represents a log entry carrying message text and severity level for IPC logging and error reporting.
 */
final readonly class LogMessage implements Message
{
    /**
     * LogMessage constructor.
     *
     * @param string $message The log message or raw payload.
     * @param string $level   The log severity level (e.g., 'info', 'error').
     */
    public function __construct(public string $message, public string $level = 'info')
    {
    }
}
