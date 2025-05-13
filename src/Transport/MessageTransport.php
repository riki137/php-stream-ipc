<?php

declare(strict_types=1);

namespace PhpStreamIpc\Transport;

use Amp\ByteStream\ClosedException;
use Amp\Cancellation;
use PhpStreamIpc\Message\Message;

/**
 * MessageTransport interface defines a contract for sending and receiving messages in a stream-based IPC system.
 *
 * Implementations of this interface should provide reliable and efficient means of transmitting messages
 * between processes or systems, while handling serialization, deserialization, and error handling.
 *
 * @package PhpStreamIpc\Transport
 */
interface MessageTransport
{
    /**
     * Sends a message through the transport.
     *
     * @param Message $message The message to send.
     * @return void
     */
    public function send(Message $message): void;

    /**
     * Reads the next message from the transport.
     *
     * @param Cancellation|null $cancellation Optional cancellation token to cancel the read operation.
     * @return Message The received message as a Message instance.
     * @throws ClosedException If the stream is closed and no more messages can be read.
     */
    public function read(?Cancellation $cancellation = null): Message;
}
