<?php

declare(strict_types=1);

namespace PhpStreamIpc\Transport;

use Amp\ByteStream\ReadableResourceStream;
use Amp\ByteStream\WritableResourceStream;
use Amp\Cancellation;
use Amp\Future;
use LogicException;
use PhpStreamIpc\Message\Message;
use PhpStreamIpc\Serialization\MessageSerializer;
use RuntimeException;
use function Amp\Future\awaitFirst;
use function pack;
use function strlen;

final class FramedStreamMessageTransport implements MessageTransport
{
    public const DEFAULT_MAX_FRAME = 10 * 1024 * 1024;

    /** @var StreamFrameReader[] */
    private array $readers = [];

    /** @var Future[] */
    private array $readerPromises = [];

    public function __construct(
        private readonly WritableResourceStream $writeStream,
        ReadableResourceStream|array $readStreams,
        private readonly MessageSerializer $serializer,
        int $frameLimit = self::DEFAULT_MAX_FRAME
    ) {
        if ($readStreams instanceof ReadableResourceStream) {
            $readStreams = [$readStreams];
        }
        foreach ($readStreams as $readStream) {
            $reader = new StreamFrameReader($readStream, $serializer, $frameLimit);
            $this->readers[] = $reader;
            $this->readerPromises[] = $reader->readFrame();
        }
        if (empty($this->readerPromises)) {
            throw new RuntimeException('At least one read stream must be provided');
        }
    }

    public function send(Message $message): void
    {
        $payload = $this->serializer->serialize($message);
        $this->writeStream->write(
            StreamFrameReader::MAGIC . pack('N', strlen($payload)) . $payload
        );
    }

    public function read(?Cancellation $cancellation = null): Message
    {
        // Race the readers, but keep the losers intact
        awaitFirst($this->readerPromises, $cancellation);

        foreach ($this->readerPromises as $key => $promise) {
            if ($promise->isCompleted()) {
                $this->readerPromises[$key] = $this->readers[$key]->readFrame();
                return $promise->await();
            }
        }

        throw new LogicException('Unexpected state: at least one reader promise is not completed');
    }
}
