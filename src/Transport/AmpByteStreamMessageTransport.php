<?php

declare(strict_types=1);

namespace PhpStreamIpc\Transport;

use Amp\ByteStream\ReadableResourceStream;
use Amp\ByteStream\WritableResourceStream;
use Amp\DeferredFuture;
use Generator;
use InvalidArgumentException;
use LogicException;
use PhpStreamIpc\IpcSession;
use PhpStreamIpc\Message\Message;
use PhpStreamIpc\Serialization\MessageSerializer;
use Revolt\EventLoop;
use function Amp\Future\awaitFirst;


class AmpByteStreamMessageTransport implements MessageTransport
{
    /** @var ReadableResourceStream[] */
    private array $readStreams;
    private FrameCodec $codec;

    /**
     * @param WritableResourceStream $writeStream
     * @param ReadableResourceStream[] $readStreams
     * @param MessageSerializer $serializer
     * @param int|null $maxFrame
     */
    public function __construct(
        private readonly WritableResourceStream $writeStream,
        array $readStreams,
        MessageSerializer $serializer,
        private readonly ?int $maxFrame = null
    ) {
        $this->codec = new FrameCodec($serializer, $maxFrame);
        foreach ($readStreams as $readStream) {
            if (!$readStream instanceof ReadableResourceStream) {
                throw new InvalidArgumentException('Read streams must be instanceof' . ReadableResourceStream::class);
            }
            $this->readStreams[] = $readStream;
        }
    }

    public function send(Message $message): void
    {
        $this->writeStream->write($this->codec->pack($message));
    }

    public function tick(array $sessions, ?float $timeout = null): void
    {
        $defs = $callbacks = [];
        [$readStream, $session] = awaitFirst($this->getFutures($sessions, $defs, $callbacks));
        foreach ($callbacks as $callbackId) {
            EventLoop::cancel($callbackId);
        }
        /** @var DeferredFuture $deferredFuture */
        foreach ($defs as $deferredFuture) {
            $deferredFuture->getFuture()->ignore();
        }

        $data = @fread($readStream->getResource(), $this->maxFrame ?? FrameCodec::DEFAULT_MAX_FRAME);
        if (is_string($data)) {
            if (!$session instanceof IpcSession) {
                throw new LogicException('Unexpected session type: ' . get_debug_type($session));
            }
            $transport = $session->getTransport();
            if (!$transport instanceof AmpByteStreamMessageTransport) {
                throw new LogicException('Unexpected transport type: ' . get_debug_type($transport));
            }
            $messages = $transport->codec->feed($data);
            foreach ($messages as $message) {
                $session->dispatch($message);
            }
        }
    }

    private function getFutures(array $sessions, array &$defs, array &$callbacks): Generator
    {
        foreach ($sessions as $session) {
            if (!$session->getTransport() instanceof AmpByteStreamMessageTransport) {
                continue;
            }
            foreach ($this->readStreams as $readStream) {
                $defs[] = $def = new DeferredFuture();
                $callbacks[] = EventLoop::onReadable($readStream->getResource(),
                    function (string $callbackId) use ($session, $def, $readStream, $sessions) {
                        if (!$def->isComplete()) {
                            $def->complete([$readStream, $session]);
                        }
                        EventLoop::cancel($callbackId);
                    });
                yield $def->getFuture();
            }
        }
    }
}
