<?php

declare(strict_types=1);

namespace PhpStreamIpc\Message;

use PhpStreamIpc\Message\Message;

/**
 * A simple message structure for transporting log entries or error reports via IPC.
 * It carries a string message and an associated severity level.
 */
final readonly class LogMessage implements Message
{
    /**
     * Constructs a new LogMessage.
     *
     * @param string $message The textual content of the log entry or error.
     * @param string $level The severity level of the log entry (e.g., 'info', 'warning', 'error'). Defaults to 'info'.
     */
    public function __construct(public string $message, public string $level = 'info')
    {
    }
}
