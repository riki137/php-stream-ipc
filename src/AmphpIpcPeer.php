<?php
declare(strict_types=1);

namespace PhpStreamIpc;

use Amp\ByteStream\ReadableResourceStream;
use Amp\ByteStream\WritableResourceStream;
use Amp\DeferredFuture;
use InvalidArgumentException;
use PhpStreamIpc\Transport\AmpByteStreamMessageTransport;
use Revolt\EventLoop;
use function Amp\Future\awaitFirst;

final class AmphpIpcPeer extends IpcPeer
{
    /**
     * @param WritableResourceStream $write
     * @param ReadableResourceStream[] $reads
     * @return IpcSession
     */
    public function createByteStreamSession(WritableResourceStream $write, array $reads): IpcSession
    {
        $readStreams = [];
        foreach ($reads as $stream) {
            if (!$stream instanceof ReadableResourceStream) {
                throw new InvalidArgumentException('Read streams must be instance of ' . ReadableResourceStream::class);
            }
            $readStreams[] = $stream;
        }
        return $this->createSession(
            new AmpByteStreamMessageTransport(
                $write,
                $readStreams,
                $this->defaultSerializer
            )
        );
    }

    public function createSessionFromTransport(AmpByteStreamMessageTransport $transport): IpcSession
    {
        return $this->createSession($transport);
    }

    public function tick(?float $timeout = null): void
    {
        $defs = [];
        $callbacks = [];

        foreach ($this->sessions as $session) {
            $transport = $session->getTransport();
            if (!$transport instanceof AmpByteStreamMessageTransport) {
                continue;
            }
            foreach ($transport->getReadStreams() as $readStream) {
                $defs[] = $def = new DeferredFuture();
                $callbacks[] = EventLoop::onReadable(
                    $readStream->getResource(),
                    static function (string $id) use ($def, $readStream, $session): void {
                        if (!$def->isComplete()) {
                            $def->complete([$readStream, $session]);
                        }
                        EventLoop::cancel($id);
                    }
                );
            }
        }

        if ($defs === []) {
            return;
        }

        $futures = (static function () use ($defs) {
            foreach ($defs as $def) {
                yield $def->getFuture();
            }
        })();

        [$stream, $session] = awaitFirst($futures);

        foreach ($callbacks as $callbackId) {
            EventLoop::cancel($callbackId);
        }
        foreach ($defs as $def) {
            $def->getFuture()->ignore();
        }

        if ($stream !== null && $session instanceof IpcSession) {
            $transport = $session->getTransport();
            if ($transport instanceof AmpByteStreamMessageTransport) {
                foreach ($transport->readFromStream($stream) as $message) {
                    $session->dispatch($message);
                }
            }
        }
    }
}
