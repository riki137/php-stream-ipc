<?php

declare(strict_types=1);

namespace StreamIpc\Serialization;

use StreamIpc\Message\Message;

/**
 * Serializes and deserializes {@see Message} objects to and from string payloads for Inter-Process Communication (IPC).
 */
interface MessageSerializer
{
    /**
     * Serializes a {@see Message} object into a string representation.
     *
     * @param $data Message The message to serialize.
     */
    public function serialize(Message $data): string;

    /**
     * Deserializes a string payload back into a {@see Message} object.
     *
     * @param $data string Serialized data to deserialize.
     */
    public function deserialize(string $data): Message;
}
