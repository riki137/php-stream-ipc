<?php

declare(strict_types=1);

namespace PhpStreamIpc\Transport;

use PhpStreamIpc\IpcSession;
use PhpStreamIpc\Message\Message;

/**
 * MessageTransport interface defines a contract for sending and receiving messages in a stream-based IPC system.
 *
 * Implementations of this interface should provide reliable and efficient means of transmitting messages
 * between processes or systems, while handling serialization, deserialization, and error handling.
 */
interface MessageTransport
{
    public function send(Message $message): void;


    /**
     * Drive I/O for all of these sessions at once.
     *
     * @param IpcSession[]    $sessions
     * @param float|null      $timeout  seconds to wait (null = block indefinitely)
     */
    public function tick(array $sessions, ?float $timeout = null): void;
}
