<?php

declare(strict_types=1);

namespace StreamIpc\Transport;

use StreamIpc\Message\Message;

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
     * @throws StreamClosedException
     */
    public function send(Message $message): void;
}
