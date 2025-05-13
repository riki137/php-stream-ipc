<?php

declare(strict_types=1);

namespace PhpStreamIpc\Serialization;

use PhpStreamIpc\Message\LogMessage;
use PhpStreamIpc\Message\Message;
use Throwable;

/**
 * Encodes Message objects via PHP's serialize() and Base64 to ensure newline-safe payloads.
 * On decode or unserialize failure, returns a LogMessage with level 'error'.
 */
final readonly class NativeMessageSerializer implements MessageSerializer
{
    /**
     * Serialize and Base64-encode a Message.
     *
     * @param Message $data The Message object to serialize.
     * @return string Base64-encoded serialized payload.
     */
    public function serialize(Message $data): string
    {
        // Base64‐encode the serialized payload so it never contains "\n"
        return serialize($data);
    }

    /**
     * Decode and unserialize a Base64 payload into a Message instance.
     *
     * On errors (invalid Base64, unserialization failure, or wrong type),
     * returns a LogMessage with level 'error'.
     *
     * @param string $data The Base64-encoded serialized message.
     * @return Message The deserialized Message or a LogMessage on error.
     */
    public function deserialize(string $data): Message
    {
        // Then try unserializing
        try {
            $result = @unserialize($data);
        } catch (Throwable $e) {
            return new LogMessage($decoded, 'error');
        }

        if ($result === false && $data !== serialize(false)) {
            return new LogMessage($data, 'error');
        }

        if (!$result instanceof Message) {
            return new LogMessage(
                "Received message does not implement Message interface: $data",
                'error'
            );
        }

        return $result;
    }
}
