<?php

declare(strict_types=1);

namespace PhpStreamIpc\Transport;

use LogicException;
use PhpStreamIpc\IpcSession;
use PhpStreamIpc\Message\Message;
use PhpStreamIpc\Serialization\MessageSerializer;
use function pack;
use function strlen;

/**
 * FramedStreamMessageTransport implements the MessageTransport interface for stream-based IPC
 * using a framed protocol. It handles sending and receiving messages by prefixing them with
 * a magic number and their length, ensuring reliable message boundary detection.
 *
 * This transport can read from multiple input streams concurrently.
 */
final class FramedStreamMessageTransport implements MessageTransport
{
    /** @var int The default maximum frame size in bytes (10MB). */
    public const DEFAULT_MAX_FRAME = 10 * 1024 * 1024;

    /** @var resource */
    private $writeStream;

    private MessageSerializer $serializer;

    /** @var StreamFrameReader[] indexed by (int)$stream */
    private array $readers = [];

    /**
     * Constructs a new FramedStreamMessageTransport.
     *
     * @param resource $writeStream The stream resource for writing messages.
     * @param resource[] $readStreams An array of stream resources for reading messages.
     * @param MessageSerializer $serializer The serializer to use for messages.
     * @param int $frameLimit The maximum allowed size for a single message frame.
     */
    public function __construct(
        $writeStream,
        array $readStreams,
        MessageSerializer $serializer,
        int $frameLimit = self::DEFAULT_MAX_FRAME
    ) {
        $this->writeStream = $writeStream;
        $this->serializer = $serializer;
        foreach ($readStreams as $stream) {
            $this->readers[(int)$stream] = new StreamFrameReader($stream, $serializer, $frameLimit);
        }
    }

    /**
     * Sends a message over the write stream.
     * The message is serialized, prefixed with a magic number and its length, and then written to the stream.
     *
     * @param Message $message The message to send.
     */
    public function send(Message $message): void
    {
        $payload = $this->serializer->serialize($message);
        fwrite(
            $this->writeStream,
            StreamFrameReader::MAGIC . pack('N', strlen($payload)) . $payload
        );
        fflush($this->writeStream);
    }

    /**
     * @throws StreamClosedException
     */
    private function readFrom($stream): array
    {
        return $this->readers[(int)$stream]->readFrameSync();
    }

    /**
     * Performs a single I/O tick for all provided sessions that use this transport type.
     * It uses `stream_select` to wait for readable data on any of the sessions' read streams.
     * When data is ready, it reads and dispatches messages.
     *
     * @param IpcSession[] $sessions An array of IPC sessions to process.
     * @param float|null $timeout Optional timeout in seconds for `stream_select`. Null means block indefinitely.
     * @throws LogicException If a session uses a different transport type.
     */
    public function tick(array $sessions, ?float $timeout = null): void
    {
        $streams = [];
        $sessionStreams = [];
        foreach ($sessions as $session) {
            $transport = $session->getTransport();
            if (!$transport instanceof FramedStreamMessageTransport) {
                throw new LogicException('FramedStreamMessageTransport cannot be mixed with other MessageTransport implementations (' . get_debug_type($transport) . ')');
            }
            foreach ($transport->readers as $reader) {
                $stream = $reader->getStream();
                $streams[(int)$stream] = $stream;
                $sessionStreams[(int)$stream] = $session;
            }
        }
        if ($streams === []) {
            return;
        }

        $reads = array_values($streams);
        $writes = $except = [];

        if ($timeout === null) {
            // infinite block
            $ready = stream_select($reads, $writes, $except, null, null);
        } else {
            $sec = (int)floor($timeout);
            $usec = (int)(($timeout - $sec) * 1e6);
            $ready = stream_select($reads, $writes, $except, $sec, $usec);
        }

        if ($ready > 0) {
            foreach ($reads as $stream) {
                $session = $sessionStreams[(int)$stream];
                try {
                    foreach ($session->getTransport()->readFrom($stream) as $msg) {
                        $session->dispatch($msg);
                    }
                } catch (StreamClosedException) {
                    // ignore
                }
            }
        }
    }
}
