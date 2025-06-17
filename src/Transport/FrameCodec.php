<?php
declare(strict_types=1);

namespace StreamIpc\Transport;

use RuntimeException;
use StreamIpc\Message\ErrorMessage;
use StreamIpc\Message\LogMessage;
use StreamIpc\Message\Message;
use StreamIpc\Serialization\MessageSerializer;
use Throwable;

final class FrameCodec
{
    public const MAGIC = "\xF3\x4A\x9D\xE2";
    private const MAGIC_LEN = 4;
    private const LEN_LEN = 4;

    private string $buffer = '';

    private int $scanPos = 0;

    public function __construct(
        private readonly MessageSerializer $serializer,
        private readonly ?int $maxFrame = null
    ) {
    }

    public function pack(Message $message): string
    {
        $payload = $this->serializer->serialize($message);
        return self::MAGIC . pack('N', strlen($payload)) . $payload;
    }

    /** @return Message[] */
    public function feed(string $chunk): array
    {
        if ($chunk !== '') {
            $this->buffer .= $chunk;
        }

        $messages = [];

        while (true) {
            // ── 1. search for next MAGIC ───────────────────────────────────────────
            $pos = strpos($this->buffer, self::MAGIC, $this->scanPos);

            if ($pos === false) {
                // ---------- NO HEADER FOUND YET ----------
                $overlap = $this->computeOverlap();
                $junkLen = strlen($this->buffer) - $overlap;

                if ($junkLen > 0) {
                    $messages[] = new LogMessage(substr($this->buffer, 0, $junkLen), LogMessage::LEVEL_JUNK);
                    $this->buffer = substr($this->buffer, $junkLen); // keep only overlap
                }
                $this->scanPos = 0;
                break;
            }

            // ── 2. junk before header ──────────────────────────────────────────────
            if ($pos > 0) {
                $messages[] = new LogMessage(substr($this->buffer, 0, $pos), LogMessage::LEVEL_JUNK);
                $this->buffer = substr($this->buffer, $pos);
                $this->scanPos = 0;
            }

            // ── 3. need at least header + length ───────────────────────────────────
            if (strlen($this->buffer) < self::MAGIC_LEN + self::LEN_LEN) {
                break;
            }

            // ── 4. parse 32-bit BE length fast ─────────────────────────────────────
            $lenOffset = self::MAGIC_LEN;
            $length = (ord($this->buffer[$lenOffset]) << 24)
                | (ord($this->buffer[$lenOffset + 1]) << 16)
                | (ord($this->buffer[$lenOffset + 2]) << 8)
                | ord($this->buffer[$lenOffset + 3]);

            if ($this->maxFrame !== null && $length > $this->maxFrame) {
                throw new RuntimeException("Frame length $length exceeds max {$this->maxFrame}");
            }

            $frameSize = self::MAGIC_LEN + self::LEN_LEN + $length;
            if (strlen($this->buffer) < $frameSize) {
                break; // wait for the rest of the frame
            }

            // ── 5. extract payload & consume buffer ────────────────────────────────
            $payload = substr(
                $this->buffer,
                self::MAGIC_LEN + self::LEN_LEN,
                $length
            );
            $this->buffer = substr($this->buffer, $frameSize);
            $this->scanPos = 0;

            try {
                $messages[] = $this->serializer->deserialize($payload);
            } catch (Throwable $e) {
                $messages[] = new ErrorMessage('Failed to deserialize message payload', $e);
            }
        }

        return $messages;
    }

    public function hasBufferedData(): bool
    {
        return $this->buffer !== '';
    }

    /** Longest suffix of buffer that matches the start of MAGIC (0-3 bytes). */
    private function computeOverlap(): int
    {
        $max = min(strlen($this->buffer), self::MAGIC_LEN - 1);
        for ($i = $max; $i > 0; $i--) {
            if (strncmp($this->buffer, self::MAGIC, $i) === 0) {
                // entire buffer *is* a prefix – keep it
                return strlen($this->buffer);
            }
            if (substr_compare($this->buffer, substr(self::MAGIC, 0, $i), -$i, $i) === 0) {
                return $i; // found matching suffix
            }
        }
        return 0;
    }
}
