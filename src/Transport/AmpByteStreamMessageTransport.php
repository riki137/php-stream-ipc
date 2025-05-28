<?php

declare(strict_types=1);

namespace StreamIpc\Transport;

use Amp\ByteStream\ReadableResourceStream;
use Amp\ByteStream\WritableResourceStream;
use StreamIpc\Message\Message;
use StreamIpc\Serialization\MessageSerializer;
use function is_resource;

/**
 * @codeCoverageIgnore Async has trouble with coverage
 */
class AmpByteStreamMessageTransport implements MessageTransport
{
    /** @var ReadableResourceStream[] */
    private array $readStreams;

    private FrameCodec $codec;

    /**
     * @param WritableResourceStream      $writeStream Stream to write to
     * @param ReadableResourceStream[]    $readStreams array of readable streams
     * @param MessageSerializer           $serializer Serializer for messages
     * @param int|null                    $maxFrame    Optional maximum frame size
     */
    public function __construct(
        private readonly WritableResourceStream $writeStream,
        array $readStreams,
        MessageSerializer $serializer,
        private readonly ?int $maxFrame = null
    ) {
        $this->codec = new FrameCodec($serializer, $maxFrame);
        foreach ($readStreams as $readStream) {
            $this->readStreams[] = $readStream;
        }
    }

    /**
     * Serialize and write the message to the writable stream.
     */
    public function send(Message $message): void
    {
        $this->writeStream->write($this->codec->pack($message));
    }

    /**
     * @return ReadableResourceStream[]
     */
    public function getReadStreams(): array
    {
        return $this->readStreams;
    }

    /**
     * @param ReadableResourceStream $stream Stream to read from
     * @return Message[]
     */
    public function readFromStream(ReadableResourceStream $stream): array
    {
        $resource = $stream->getResource();
        if (!is_resource($resource)) {
            return [];
        }
        $length = $this->maxFrame ?? FrameCodec::DEFAULT_MAX_FRAME;
        $data = @fread($resource, $length > 0 ? $length : 1);
        if (!is_string($data)) {
            return [];
        }

        return $this->codec->feed($data);
    }
}
