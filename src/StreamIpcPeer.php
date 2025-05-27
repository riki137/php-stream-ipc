<?php
declare(strict_types=1);

namespace PhpStreamIpc;

use PhpStreamIpc\Transport\StreamMessageTransport;

final class StreamIpcPeer extends IpcPeer
{
    public function createStreamSession($write, $read, $read2 = null): IpcSession
    {
        $reads = [$read];
        if ($read2 !== null) {
            $reads[] = $read2;
        }
        return $this->createSession(
            new StreamMessageTransport(
                $write,
                $reads,
                $this->defaultSerializer
            )
        );
    }

    public function createStdioSession(): IpcSession
    {
        return $this->createStreamSession(STDOUT, STDIN);
    }

    public function createSessionFromTransport(StreamMessageTransport $transport): IpcSession
    {
        return $this->createSession($transport);
    }

    public function tick(?float $timeout = null): void
    {
        if ($this->sessions === []) {
            return;
        }
        $transport = $this->sessions[0]->getTransport();
        if ($transport instanceof StreamMessageTransport) {
            $transport->tick($this->sessions, $timeout);
        }
    }
}
