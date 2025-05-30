<?php

declare(strict_types=1);

namespace StreamIpc\Transport;

use StreamIpc\Message\Message;
use StreamIpc\Serialization\MessageSerializer;
use function feof;
use function fread;

/**
 * Reads framed messages from a stream.
 * It expects messages to be prefixed with a magic number and a 4-byte length header.
 * Handles partial reads and reassembles full message frames.
 * If invalid data or framing errors are encountered, it may return a LogMessage.
 */
final class NativeFrameReader
{
    private FrameCodec $codec;

    /**
     * Constructs a new StreamFrameReader.
     *
     * @param $stream resource Stream to read from.
     * @param $serializer MessageSerializer used for incoming frames.
     * @param $maxFrame ?int Maximum allowed size for a single message frame.
     */
    /**
     * @param resource $stream
     */
    public function __construct(
        private $stream,
        MessageSerializer $serializer,
        ?int $maxFrame = null
    ) {
        $this->codec = new FrameCodec($serializer, $maxFrame);
    }

    /**
     * Gets the underlying stream resource.
     *
     * @return resource
     */
    public function getStream()
    {
        return $this->stream;
    }

    /**
     * Reads one or more message frames from the stream synchronously (blocking).
     * Continues reading until at least one full frame is parsed, then returns
     * every complete frame currently buffered as an array of Message objects.
     *
     * If the stream is closed before any complete frame is read, a StreamClosedException is thrown.
     * Invalid framing or oversized frames produce LogMessage entries.
     *
     * @return Message[] Array of deserialized messages (or LogMessage on errors)
     * @throws StreamClosedException If the stream is closed before any complete frame is available
     */
    public function readFrameSync(): array
    {
        while (true) {
            $chunk = fread($this->stream, 8192);
            if ($chunk === '' || $chunk === false) {
                if (feof($this->stream)) {
                    if (!$this->codec->hasBufferedData()) {
                        throw new StreamClosedException();
                    }
                } else {
                    // no data right now, retry
                    continue;
                }
            }

            $messages = $this->codec->feed($chunk ?: '');
            if ($messages !== []) {
                return $messages;
            }
        }
    }
}
