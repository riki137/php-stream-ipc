<?php

declare(strict_types=1);

namespace PhpStreamIpc\Transport;

use Amp\ByteStream\ReadableResourceStream;
use Amp\ByteStream\WritableResourceStream;
use InvalidArgumentException;
use PhpStreamIpc\Message\Message;
use PhpStreamIpc\Serialization\MessageSerializer;

/**
 * @codeCoverageIgnore Async has trouble with coverage
 */
class AmpByteStreamMessageTransport implements MessageTransport
{
    /** @var ReadableResourceStream[] */
    private array $readStreams;

    private FrameCodec $codec;

    /**
     * @param $writeStream WritableResourceStream to write to.
     * @param $readStreams array of readable stream resources.
     * @param $serializer MessageSerializer used for messages.
     * @param $maxFrame ?int Optional maximum frame size. Defaults to 10MB.
     */
    public function __construct(
        private readonly WritableResourceStream $writeStream,
        array $readStreams,
        MessageSerializer $serializer,
        private readonly ?int $maxFrame = null
    ) {
        $this->codec = new FrameCodec($serializer, $maxFrame);
        foreach ($readStreams as $readStream) {
            if (!$readStream instanceof ReadableResourceStream) {
                throw new InvalidArgumentException('Read streams must be instanceof' . ReadableResourceStream::class);
            }
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
     * @param $stream ReadableResourceStream to read from.
     * @return Message[]
     */
    public function readFromStream(ReadableResourceStream $stream): array
    {
        $data = @fread($stream->getResource(), $this->maxFrame ?? FrameCodec::DEFAULT_MAX_FRAME);
        if (!is_string($data)) {
            return [];
        }

        return $this->codec->feed($data);
    }
}
