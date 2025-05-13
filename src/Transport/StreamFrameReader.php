<?php

declare(strict_types=1);

namespace PhpStreamIpc\Transport;

use Amp\ByteStream\ReadableResourceStream;
use Amp\Cancellation;
use Amp\Future;
use PhpStreamIpc\Message\LogMessage;
use PhpStreamIpc\Message\Message;
use PhpStreamIpc\Serialization\MessageSerializer;
use Throwable;
use function Amp\async;
use function strlen;
use function strpos;
use function substr;
use function unpack;

/**
 * @internal
 */
final class StreamFrameReader
{
    public const MAGIC = "\xF3\x4A\x9D\xE2";
    public const HEADER_SIZE = 8;

    private string $buffer = '';

    public function __construct(
        private readonly ReadableResourceStream $stream,
        private readonly MessageSerializer $serializer,
        private readonly int $maxFrame,
    ) {
    }

    /**
     * Reads a frame from the stream.
     *
     * @return Future<Message>
     */
    public function readFrame(?Cancellation $cancellation = null): Future
    {
        return async(function () use ($cancellation) {
            while (!$cancellation?->isRequested()) {
                $chunk = $this->stream->read($cancellation);
                if ($chunk !== null) {
                    $this->buffer .= $chunk;
                }

                while (true) {
                    $pos = strpos($this->buffer, self::MAGIC);
                    if ($pos === false) {
                        if (strlen($this->buffer) > strlen(self::MAGIC)) {
                            $junk = $this->buffer;
                            $this->buffer = '';
                            return new LogMessage($junk, 'error');
                        }
                        break;
                    }

                    if ($pos > 0) {
                        $junk = substr($this->buffer, 0, $pos);
                        $this->buffer = substr($this->buffer, $pos);
                        return new LogMessage($junk, 'error');
                    }

                    if (strlen($this->buffer) < self::HEADER_SIZE) {
                        break;
                    }

                    $length = unpack('N', substr($this->buffer, 4, 4))[1];
                    if ($length > $this->maxFrame) {
                        $this->buffer = substr($this->buffer, 1);
                        continue;
                    }

                    if (strlen($this->buffer) < self::HEADER_SIZE + $length) {
                        break;
                    }

                    $payload = substr($this->buffer, self::HEADER_SIZE, $length);
                    $this->buffer = substr($this->buffer, self::HEADER_SIZE + $length);

                    try {
                        return $this->serializer->deserialize($payload);
                    } catch (Throwable $e) {
                        return new LogMessage($payload, 'error');
                    }
                }
            }
        });
    }
}
