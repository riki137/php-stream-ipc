<?php

declare(strict_types=1);

namespace PhpStreamIpc\Transport;

use PhpStreamIpc\Message\LogMessage;
use PhpStreamIpc\Message\Message;
use PhpStreamIpc\Serialization\MessageSerializer;
use Throwable;
use function feof;
use function fread;
use function strlen;
use function strpos;
use function substr;
use function unpack;

/**
 * Reads framed messages from a stream.
 * It expects messages to be prefixed with a magic number and a 4-byte length header.
 * Handles partial reads and reassembles full message frames.
 * If invalid data or framing errors are encountered, it may return a LogMessage.
 */
final class StreamFrameReader
{
    public const MAGIC = "\xF3\x4A\x9D\xE2";

    /** @var array<int,string> */
    private static array $magicPrefixes = [];

    private static function initMagicPrefixes(): void
    {
        if (self::$magicPrefixes !== []) {
            return;
        }

        $magic = self::MAGIC;
        $len = strlen($magic);
        for ($i = $len - 1; $i >= 1; $i--) {
            self::$magicPrefixes[$i] = substr($magic, 0, $i);
        }
    }

    private string $buffer = '';

    /**
     * Constructs a new StreamFrameReader.
     *
     * @param resource $stream The stream to read from.
     * @param MessageSerializer $serializer The serializer to use for deserializing message payloads.
     * @param int $maxFrame The maximum allowed size for a single message frame.
     */
    public function __construct(
        private $stream,
        private readonly MessageSerializer $serializer,
        private readonly int $maxFrame
    ) {
        self::initMagicPrefixes();
    }

    /**
     * Gets the underlying stream resource.
     *
     * @return resource The stream resource being read from.
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
     * @return Message[]  Array of deserialized messages (or LogMessage on errors)
     * @throws StreamClosedException If the stream is closed before any complete frame is available
     */
    public function readFrameSync(): array
    {
        $magicLen = strlen(self::MAGIC);
        $messages = [];

        while (true) {
            // 1) Read and buffer
            $chunk = fread($this->stream, 8192);
            if ($chunk === '' || $chunk === false) {
                if (feof($this->stream)) {
                    if (strlen($this->buffer) === 0) {
                        throw new StreamClosedException();
                    }
                    // fall through to parsing whatever's left in buffer
                } else {
                    // no data right now, retry
                    continue;
                }
            } else {
                $this->buffer .= $chunk;
            }

            // 2) Parse out as many full frames (or junk) as possible
            while (true) {
                $pos = strpos($this->buffer, self::MAGIC);

                // -- no magic at all yet
                if ($pos === false) {
                    if (strlen($this->buffer) > $magicLen) {
                        // only inspect at most (magicLen-1) bytes:
                        $overlap = $this->getOverlapLength();
                        if ($overlap > 0) {
                            // emit the true junk
                            $messages[] = new LogMessage(substr($this->buffer, 0, -$overlap), 'error');
                            // keep just the potential MAGIC prefix
                            $this->buffer = substr($this->buffer, -$overlap);
                        } else {
                            // emit the whole buffer as junk
                            $messages[] = new LogMessage($this->buffer, 'error');
                            $this->buffer = '';
                        }
                        break;
                    }
                }

                // -- junk before magic
                if ($pos > 0) {
                    $junk = substr($this->buffer, 0, $pos);
                    $this->buffer = substr($this->buffer, $pos);
                    $messages[] = new LogMessage($junk, 'error');
                    continue;
                }

                // -- need at least 8 bytes (magic + length) to proceed
                if (strlen($this->buffer) < 8) {
                    break;
                }

                // -- pull length
                $length = unpack('N', substr($this->buffer, 4, 4))[1];
                if ($length > $this->maxFrame) {
                    // skip one byte and resync
                    $this->buffer = substr($this->buffer, 1);
                    continue;
                }

                // -- wait for full payload
                if (strlen($this->buffer) < 8 + $length) {
                    break;
                }

                // -- consume a full frame
                $payload = substr($this->buffer, 8, $length);
                $this->buffer = substr($this->buffer, 8 + $length);

                try {
                    $messages[] = $this->serializer->deserialize($payload);
                } catch (Throwable $e) {
                    $messages[] = new LogMessage($payload, 'error');
                }
            }

            // 3) if we got at least one message, return them now
            if (!empty($messages)) {
                return $messages;
            }

            // otherwise, loop back and read more
        }
    }

    /**
     * Only looks at the last up to (magicLen-1) bytes and
     * returns how many of them match the start of MAGIC.
     */
    private function getOverlapLength(): int
    {
        $bufLen = strlen($this->buffer);
        self::initMagicPrefixes();
        foreach (self::$magicPrefixes as $len => $prefix) {
            if ($bufLen >= $len
                && substr_compare(
                    $this->buffer,
                    $prefix,
                    $bufLen - $len,
                    $len
                ) === 0
            ) {
                return $len;
            }
        }

        return 0;
    }
}
