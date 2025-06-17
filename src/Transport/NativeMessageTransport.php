<?php

declare(strict_types=1);

namespace StreamIpc\Transport;

use LogicException;
use StreamIpc\Message\Message;
use StreamIpc\Serialization\MessageSerializer;
use StreamIpc\Transport\FrameCodec;

/**
 * FramedStreamMessageTransport implements the MessageTransport interface for stream-based IPC
 * using a framed protocol. It handles sending and receiving messages by prefixing them with
 * a magic number and their length, ensuring reliable message boundary detection.
 *
 * This transport can read from multiple input streams concurrently.
 */
final class NativeMessageTransport implements MessageTransport
{
    /** @var resource */
    private $writeStream;

    private FrameCodec $codec;

    /** @var NativeFrameReader[] indexed by (int)$stream */
    private array $readers = [];

    /**
     * Constructs a new FramedStreamMessageTransport.
     *
     * @param resource              $writeStream Stream resource for writing messages
     * @param array<int, resource>  $readStreams Array of stream resources for reading messages
     * @param MessageSerializer     $serializer  Serializer used for messages
     * @param int|null              $frameLimit  Maximum allowed size for a single frame
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
            $this->readers[(int)$stream] = new NativeFrameReader($stream, $serializer, $frameLimit);
        }
    }

    /**
     * Sends a message over the write stream.
     * The message is serialized, prefixed with a magic number and its length, and then written to the stream.
     *
     * @param Message $message The message to send.
     * @throws StreamClosedException If the write stream is closed or writing fails.
     */
    public function send(Message $message): void
    {
        $data = $this->codec->pack($message);

        // Clear any previous PHP error
        error_clear_last();

        // Suppress the warning and attempt to write
        $bytesWritten = @fwrite($this->writeStream, $data);

        if ($bytesWritten < 1) {
            // Check for any "Broken pipe" error
            $lastError = error_get_last();
            if ($lastError !== null && stripos($lastError['message'], 'Broken pipe') !== false) {
                throw new StreamClosedException('Broken pipe while writing to stream:' . $lastError['message']);
            }
        }

        if ($bytesWritten === false) {
            throw new StreamClosedException('Unable to write payload to stream');
        } else {
            fflush($this->writeStream);
        }
    }

    /**
     * @return resource[] Streams that can be used for reading messages.
     */
    public function getReadStreams(): array
    {
        $streams = [];
        foreach ($this->readers as $reader) {
            $streams[] = $reader->getStream();
        }

        return $streams;
    }

    /**
     * Read and decode any available messages from the given stream.
     *
     * @param resource $stream Stream resource to read from
     * @return Message[]
     */
    public function readFromStream($stream): array
    {
        $reader = $this->readers[(int) $stream] ?? null;
        if ($reader === null) {
            throw new LogicException('Unknown stream provided to readFromStream');
        }

        try {
            return $reader->readFrameSync();
        } catch (StreamClosedException) {
            return [];
        }
    }
}
