<?php
declare(strict_types=1);

namespace PhpStreamIpc;

use PhpStreamIpc\Transport\AmpByteStreamMessageTransport;
use Amp\ByteStream\ReadableResourceStream;
use Amp\ByteStream\WritableResourceStream;

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
                throw new \InvalidArgumentException('Read streams must be instance of '.ReadableResourceStream::class);
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
        if ($this->sessions === []) {
            return;
        }
        $transport = $this->sessions[0]->getTransport();
        if ($transport instanceof AmpByteStreamMessageTransport) {
            $transport->tick($this->sessions, $timeout);
        }
    }
}
