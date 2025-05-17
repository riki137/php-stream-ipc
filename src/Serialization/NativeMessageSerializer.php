<?php

declare(strict_types=1);

namespace PhpStreamIpc\Serialization;

use PhpStreamIpc\Message\LogMessage;
use PhpStreamIpc\Message\Message;
use Throwable;

/**
 * Encodes Message objects via PHP's serialize().
 * On decode or unserialize failure, returns a LogMessage with level 'error'.
 */
final readonly class NativeMessageSerializer implements MessageSerializer
{
    /**
     * Serializes a {@see Message} object into a string using PHP's native `serialize()` function.
     *
     * @param Message $data The {@see Message} object to serialize.
     * @return string The serialized string representation of the message.
     */
    public function serialize(Message $data): string
    {
        return serialize($data);
    }

    /**
     * Deserializes a string payload back into a {@see Message} object using PHP's native `unserialize()` function.
     * If unserialization fails or the resulting object does not implement {@see Message},
     * a {@see LogMessage} with level 'error' is returned containing the original data.
     *
     * @param string $data The string payload to deserialize.
     * @return Message The deserialized {@see Message} object, or a {@see LogMessage} on failure.
     */
    public function deserialize(string $data): Message
    {
        // Then try unserializing
        try {
            $result = @unserialize($data);
        } catch (Throwable $e) {
            return new LogMessage($data, 'error');
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
