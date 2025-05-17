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
     * Drives I/O for all specified IPC sessions that use this transport.
     * This method should typically be called in a loop to process incoming messages and manage stream activity.
     * Implementations might use mechanisms like `stream_select` to efficiently wait for data on multiple streams.
     *
     * @param IpcSession[] $sessions An array of IpcSession objects whose I/O should be processed.
     *                               All sessions must be using this transport instance.
     * @param float|null $timeout The maximum time in seconds to wait for I/O activity. 
     *                            A value of `null` means to block indefinitely until activity occurs.
     *                            A value of `0` means to check for I/O without blocking.
     */
    public function tick(array $sessions, ?float $timeout = null): void;
}
