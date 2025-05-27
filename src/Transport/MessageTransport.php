<?php

declare(strict_types=1);

namespace PhpStreamIpc\Transport;

use PhpStreamIpc\Message\Message;

/**
 * MessageTransport interface defines a contract for sending and receiving messages in a stream-based IPC system.
 *
 * Implementations of this interface should provide reliable and efficient means of transmitting messages
 * between processes or systems, while handling serialization, deserialization, and error handling.
 */
interface MessageTransport
{
    /**
     * Send a {@see Message} over the underlying transport.
     */
    public function send(Message $message): void;
}
