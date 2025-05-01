<?php

declare(strict_types=1);

namespace PhpStreamIpc\Serialization;

use PhpStreamIpc\Message\LogMessage;
use PhpStreamIpc\Message\Message;
use Throwable;

final readonly class NativeMessageSerializer implements MessageSerializer
{
    public function serialize(Message $data): string
    {
        // Base64‐encode the serialized payload so it never contains "\n"
        return base64_encode(serialize($data));
    }

    public function deserialize(string $data): Message
    {
        // First, base64‐decode; if it's invalid, log an error
        $decoded = base64_decode($data, true);
        if ($decoded === false) {
            return new LogMessage($data, 'error');
        }

        // Then try unserializing
        try {
            $result = unserialize($decoded);
        } catch (Throwable $e) {
            return new LogMessage($decoded, 'error');
        }

        if ($result === false && $decoded !== serialize(false)) {
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
