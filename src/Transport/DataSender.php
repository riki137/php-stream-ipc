<?php

declare(strict_types=1);

namespace PhpStreamIpc\Transport;

/**
 * Defines a contract for sending serialized message payloads over a transport, such as a stream or socket.
 */
interface DataSender
{
    /**
     * Send a serialized message payload.
     *
     * @param string $message The serialized message string to send (without trailing newline).
     * @return void
     */
    public function send(string $message): void;
}
