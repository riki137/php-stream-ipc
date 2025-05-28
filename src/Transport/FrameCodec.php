<?php

declare(strict_types=1);

namespace StreamIpc\Transport;

use StreamIpc\Message\LogMessage;
use StreamIpc\Message\Message;
use StreamIpc\Serialization\MessageSerializer;
use Throwable;

/**
 * Handles framing of messages using a magic header and length prefix.
 * Can encode {@see Message} objects to a framed binary string and
 * parse framed bytes from an arbitrary buffer without relying on stream
 * resources or `stream_select`.
 */
final class FrameCodec
{
    /** Magic bytes indicating the start of a frame. */
    public const MAGIC = "\xF3\x4A\x9D\xE2";

    /** @var int The default maximum frame size in bytes (10MB). */
    public const DEFAULT_MAX_FRAME = 10 * 1024 * 1024;

    /** @var array<int,string> */
    private static array $magicPrefixes = [];

    private string $buffer = '';

    private readonly int $maxFrame;

    public function __construct(
        private readonly MessageSerializer $serializer,
        ?int $maxFrame = null
    ) {
        $this->maxFrame = $maxFrame ?? self::DEFAULT_MAX_FRAME;
        self::initMagicPrefixes();
    }

    /** Encode a message into a framed binary string. */
    public function pack(Message $message): string
    {
        $payload = $this->serializer->serialize($message);
        return self::MAGIC . pack('N', strlen($payload)) . $payload;
    }

    /**
     * Feed raw bytes into the decoder and return any complete messages.
     *
     * @return Message[]
     */
    public function feed(string $chunk): array
    {
        if ($chunk !== '') {
            $this->buffer .= $chunk;
        }

        $magicLen = strlen(self::MAGIC);
        $messages = [];

        while (true) {
            $pos = strpos($this->buffer, self::MAGIC);

            if ($pos === false) {
                if (strlen($this->buffer) > $magicLen) {
                    $overlap = $this->getOverlapLength();
                    if ($overlap > 0) {
                        $messages[] = new LogMessage(substr($this->buffer, 0, -$overlap), 'error');
                        $this->buffer = substr($this->buffer, -$overlap);
                    } else {
                        $messages[] = new LogMessage($this->buffer, 'error');
                        $this->buffer = '';
                    }
                    continue;
                }
                break;
            }

            if ($pos > 0) {
                $junk = substr($this->buffer, 0, $pos);
                $this->buffer = substr($this->buffer, $pos);
                $messages[] = new LogMessage($junk, 'error');
                continue;
            }

            if (strlen($this->buffer) < 8) {
                break;
            }

            $unpacked = unpack('N', substr($this->buffer, 4, 4));
            if ($unpacked === false) {
                break;
            }
            $length = $unpacked[1];
            if ($length > $this->maxFrame) {
                $this->buffer = substr($this->buffer, 1);
                continue;
            }

            if (strlen($this->buffer) < 8 + $length) {
                break;
            }

            $payload = substr($this->buffer, 8, $length);
            $this->buffer = substr($this->buffer, 8 + $length);

            try {
                $messages[] = $this->serializer->deserialize($payload);
            } catch (Throwable $e) {
                $messages[] = new LogMessage($payload, 'error');
            }
        }

        return $messages;
    }

    /**
     * Returns true when partial data is buffered but no complete frame is available yet.
     */
    public function hasBufferedData(): bool
    {
        return $this->buffer !== '';
    }

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

    /**
     * Only looks at the last up to (magicLen-1) bytes and returns how many of them
     * match the start of MAGIC.
     */
    private function getOverlapLength(): int
    {
        $bufLen = strlen($this->buffer);
        foreach (self::$magicPrefixes as $len => $prefix) {
            if ($bufLen >= $len
                && substr_compare($this->buffer, $prefix, $bufLen - $len, $len) === 0
            ) {
                return $len;
            }
        }

        return 0;
    }
}
