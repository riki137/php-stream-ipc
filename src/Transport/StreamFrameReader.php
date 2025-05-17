<?php

declare(strict_types=1);

namespace PhpStreamIpc\Transport;

use PhpStreamIpc\Message\LogMessage;
use PhpStreamIpc\Message\Message;
use PhpStreamIpc\Serialization\MessageSerializer;
use RuntimeException;
use Throwable;
use function fread;
use function feof;
use function strlen;
use function strpos;
use function substr;
use function unpack;

final class StreamFrameReader
{
    public const MAGIC = "\xF3\x4A\x9D\xE2";

    private string $buffer = '';

    public function __construct(
        private $stream,
        private readonly MessageSerializer $serializer,
        private readonly int $maxFrame
    ) {
    }

    public function getStream()
    {
        return $this->stream;
    }

    /**
     * @throws StreamClosedException
     */
    public function readFrameSync(): Message
    {
        $magicLen = strlen(self::MAGIC);

        while (true) {
            $chunk = fread($this->stream, 8192);
            if ($chunk === '' || $chunk === false) {
                if (feof($this->stream)) {
                    if (strlen($this->buffer) === 0) {
                        throw new StreamClosedException();
                    }
                    // otherwise, fall through and parse what's already in $this->buffer
                } else {
                    // no data right now, try again
                    continue;
                }
            } else {
                $this->buffer .= $chunk;
            }

            $pos = strpos($this->buffer, self::MAGIC);

            // no magic found yet
            if ($pos === false) {
                // if buffer is so big it can't possibly contain MAGIC any more...
                if (strlen($this->buffer) > $magicLen) {
                    // only discard up to the point where a full MAGIC could still begin
                    $discard = strlen($this->buffer) - ($magicLen - 1);
                    $junk    = substr($this->buffer, 0, $discard);
                    $this->buffer = substr($this->buffer, $discard);
                    return new LogMessage($junk, 'error');
                }
                continue;
            }

            // magic not at start → junk before it
            if ($pos > 0) {
                $junk = substr($this->buffer, 0, $pos);
                $this->buffer = substr($this->buffer, $pos);
                return new LogMessage($junk, 'error');
            }

            // too small to even contain length header
            if (strlen($this->buffer) < 8) {
                continue;
            }

            $length = unpack('N', substr($this->buffer, 4, 4))[1];
            if ($length > $this->maxFrame) {
                // skip one byte and re-search
                $this->buffer = substr($this->buffer, 1);
                continue;
            }

            // not arrived yet
            if (strlen($this->buffer) < 8 + $length) {
                continue;
            }

            $payload = substr($this->buffer, 8, $length);
            $this->buffer = substr($this->buffer, 8 + $length);

            try {
                return $this->serializer->deserialize($payload);
            } catch (Throwable $e) {
                return new LogMessage($payload, 'error');
            }
        }
    }
}
