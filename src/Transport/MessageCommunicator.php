<?php

declare(strict_types=1);

namespace PhpStreamIpc\Transport;

use Amp\Cancellation;
use PhpStreamIpc\Message\Message;
use PhpStreamIpc\Serialization\MessageSerializer;
use PhpStreamIpc\Serialization\NativeMessageSerializer;

/**
 * Bridges DataReader and DataSender with a MessageSerializer to transparently
 * send and receive Message objects over IPC transports.
 * Accepts a custom serializer or defaults to NativeMessageSerializer.
 */
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

    /**
     * Serialize a Message and send it via the DataSender.
     *
     * @param Message $message The Message instance to send.
     * @return void
     */
    public function send(Message $message): void
    {
        $this->sender->send($this->serializer->serialize($message));
    }

    /**
     * Read and deserialize the next message from the DataReader.
     *
     * @param Cancellation|null $cancellation Optional cancellation token.
     * @return Message The deserialized Message object.
     */
    public function read(?Cancellation $cancellation = null): Message
    {
        return $this->serializer->deserialize($this->reader->read($cancellation));
    }
}
