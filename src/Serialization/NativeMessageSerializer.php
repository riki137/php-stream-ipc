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
     * Serializes a {@see Message} object using PHP's native `serialize()` function.
     *
     * @param $data Message The message to serialize.
     */
    public function serialize(Message $data): string
    {
        return serialize($data);
    }

    /**
     * Deserializes a string payload back into a {@see Message} using PHP's `unserialize()`.
     * If unserialization fails or the result does not implement {@see Message}, an error log is returned.
     *
     * @param $data string The string payload to deserialize.
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
