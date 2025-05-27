<?php

declare(strict_types=1);

namespace PhpStreamIpc\Transport;

use LogicException;
use PhpStreamIpc\IpcSession;
use PhpStreamIpc\Message\Message;
use PhpStreamIpc\Serialization\MessageSerializer;
use PhpStreamIpc\Transport\FrameCodec;

/**
 * FramedStreamMessageTransport implements the MessageTransport interface for stream-based IPC
 * using a framed protocol. It handles sending and receiving messages by prefixing them with
 * a magic number and their length, ensuring reliable message boundary detection.
 *
 * This transport can read from multiple input streams concurrently.
 */
final class StreamMessageTransport implements MessageTransport
{
    /** @var resource */
    private $writeStream;


    private FrameCodec $codec;

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
        ?int $frameLimit = null
    ) {
        $this->writeStream = $writeStream;
        $this->codec = new FrameCodec($serializer, $frameLimit);
        foreach ($readStreams as $stream) {
            $this->readers[(int)$stream] = new StreamFrameReader($stream, $serializer, $frameLimit);
        }
    }

    /**
     * Sends a message over the write stream.
     * The message is serialized, prefixed with a magic number and its length, and then written to the stream.
     *
     * @param Message $message The message to send.
     * @throws StreamClosedException If the write stream is closed (e.g. broken pipe) or write fails.
     */
    public function send(Message $message): void
    {
        $data = $this->codec->pack($message);

        // Clear any previous PHP error
        if (function_exists('error_clear_last')) {
            error_clear_last();
        }

        // Suppress the warning and attempt to write
        $bytesWritten = @fwrite($this->writeStream, $data);

        // Check for any "Broken pipe" error
        $lastError = error_get_last();
        if ($lastError !== null && stripos($lastError['message'], 'Broken pipe') !== false) {
            throw new StreamClosedException('Broken pipe while writing to stream:' . $lastError['message']);
        }

        if ($bytesWritten === false) {
            throw new StreamClosedException('Unable to write payload to stream');
        } else {
            fflush($this->writeStream);
        }
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
            if (!$transport instanceof StreamMessageTransport) {
                continue;
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
                if (!$session instanceof IpcSession) {
                    throw new LogicException('Unexpected session type: ' . get_debug_type($session));
                }
                try {
                    $messageTransport = $session->getTransport();
                    if (!$messageTransport instanceof StreamMessageTransport) {
                        throw new LogicException('Unexpected transport type: ' . get_debug_type($messageTransport));
                    }
                    foreach ($messageTransport->readers[(int)$stream]->readFrameSync() as $msg) {
                        $session->dispatch($msg);
                    }
                } catch (StreamClosedException) {
                    // ignore
                }
            }
        }
    }
}
