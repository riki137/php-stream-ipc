<?php
declare(strict_types=1);

namespace PhpStreamIpc;

use PhpStreamIpc\Transport\SymfonyProcessMessageTransport;
use Symfony\Component\Process\Process;

final class SymfonyIpcPeer extends IpcPeer
{
    public function createSymfonyProcessSession(Process $process): IpcSession
    {
        return $this->createSession(
            new SymfonyProcessMessageTransport(
                $process,
                $this->defaultSerializer
            )
        );
    }

    public function createSessionFromTransport(SymfonyProcessMessageTransport $transport): IpcSession
    {
        return $this->createSession($transport);
    }

    public function tick(?float $timeout = null): void
    {
        if ($this->sessions === []) {
            return;
        }
        $transport = $this->sessions[0]->getTransport();
        if ($transport instanceof SymfonyProcessMessageTransport) {
            $transport->tick($this->sessions, $timeout);
        }
    }
}
