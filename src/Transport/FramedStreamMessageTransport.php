<?php

declare(strict_types=1);

namespace PhpStreamIpc\Transport;

use LogicException;
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
    public const DEFAULT_MAX_FRAME = 10 * 1024 * 1024;

    /** @var resource */
    private $writeStream;

    private MessageSerializer $serializer;

    /** @var StreamFrameReader[] indexed by (int)$stream */
    private array $readers = [];

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
    public function readFrom($stream): Message
    {
        return $this->readers[(int)$stream]->readFrameSync();
    }

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
                    $msg = $session->getTransport()->readFrom($stream);
                    $session->dispatch($msg);
                } catch (StreamClosedException) {
                    // ignore
                }
            }
        }
    }
}
