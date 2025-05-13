<?php

declare(strict_types=1);

namespace PhpStreamIpc\Serialization;

use PhpStreamIpc\Message\Message;

/**
 * Serializes and deserializes Message objects to and from string payloads for IPC transport.
 */
interface MessageSerializer
{
    /**
     * Convert a Message into a string payload.
     *
     * @param Message $data The Message instance to serialize.
     * @return string The serialized string payload.
     */
    public function serialize(Message $data): string;

    /**
     * Deserialize a string payload into a Message instance.
     *
     * @param string $data The serialized message payload.
     * @return Message The deserialized Message object.
     */
    public function deserialize(string $data): Message;
}
