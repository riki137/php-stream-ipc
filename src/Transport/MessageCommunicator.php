<?php

declare(strict_types=1);

namespace PhpStreamIpc\Transport;

use Amp\Cancellation;
use PhpStreamIpc\Message\Message;
use PhpStreamIpc\Serialization\MessageSerializer;
use PhpStreamIpc\Serialization\NativeMessageSerializer;

final readonly class MessageCommunicator
{
    private MessageSerializer $serializer;

    public function __construct(
        private DataReader $reader,
        private DataSender $sender,
        ?MessageSerializer $serializer = null,
    ) {
        $this->serializer = $serializer ?? new NativeMessageSerializer();
    }

    public function send(Message $message): void
    {
        $this->sender->send($this->serializer->serialize($message));
    }

    public function read(?Cancellation $cancellation = null): Message
    {
        return $this->serializer->deserialize($this->reader->read($cancellation));
    }
}
