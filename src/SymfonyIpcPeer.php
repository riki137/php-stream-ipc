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
        $start = microtime(true);
        $timeout ??= 0.0;
        $sleepTick = 500;

        while (true) {
            $handled = false;
            foreach ($this->sessions as $session) {
                $transport = $session->getTransport();
                if (!$transport instanceof SymfonyProcessMessageTransport) {
                    continue;
                }
                if (!$transport->isRunning()) {
                    continue;
                }
                $sleepTick = $transport->getSleepTick();
                foreach ($transport->takePending() as $messages) {
                    foreach ($messages as $message) {
                        $session->dispatch($message);
                    }
                }
                $handled = true;
            }

            if ($handled || $timeout === 0.0 || microtime(true) - $start >= $timeout) {
                return;
            }

            usleep($sleepTick);
        }
    }
}
