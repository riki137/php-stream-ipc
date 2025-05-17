<?php

declare(strict_types=1);

namespace PhpStreamIpc\Serialization;

use PhpStreamIpc\Message\Message;

/**
 * Serializes and deserializes {@see Message} objects to and from string payloads for Inter-Process Communication (IPC).
 */
interface MessageSerializer
{
    /**
     * Serializes a {@see Message} object into a string representation.
     *
     * @param Message $data The {@see Message} object to serialize.
     * @return string The serialized string representation of the message.
     */
    public function serialize(Message $data): string;

    /**
     * Deserializes a string payload back into a {@see Message} object.
     *
     * @param string $data The string payload to deserialize.
     * @return Message The deserialized {@see Message} object.
     */
    public function deserialize(string $data): Message;
}
