<?php
declare(strict_types=1);

namespace StreamIpc;

use Amp\ByteStream\ReadableResourceStream;
use Amp\ByteStream\WritableResourceStream;
use Amp\DeferredFuture;
use Revolt\EventLoop;
use StreamIpc\Transport\AmpByteStreamMessageTransport;
use function Amp\Future\awaitFirst;
use function is_resource;

/**
 * @codeCoverageIgnore Fibers have trouble with coverage
 */
final class AmphpIpcPeer extends IpcPeer
{
    /**
     * Create a session from Amp byte-streams.
     *
     * @param WritableResourceStream   $write Writable stream used for output
     * @param ReadableResourceStream[] $reads Array of readable streams used for input
     */
    public function createByteStreamSession(WritableResourceStream $write, array $reads): IpcSession
    {
        $readStreams = [];
        foreach ($reads as $stream) {
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

    /**
     * Register an existing {@see AmpByteStreamMessageTransport} instance.
     */
    public function createSessionFromTransport(AmpByteStreamMessageTransport $transport): IpcSession
    {
        return $this->createSession($transport);
    }

    /**
     * Drive all sessions by waiting for data on their read streams.
     */
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
                $resource = $readStream->getResource();
                if (!is_resource($resource)) {
                    continue;
                }
                $defs[] = $def = new DeferredFuture();
                $callbacks[] = EventLoop::onReadable(
                    $resource,
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
