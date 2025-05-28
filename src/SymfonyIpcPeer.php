<?php
declare(strict_types=1);

namespace StreamIpc;

use StreamIpc\Envelope\Id\RequestIdGenerator;
use StreamIpc\Serialization\MessageSerializer;
use StreamIpc\Transport\SymfonyProcessMessageTransport;
use Symfony\Component\Process\Process;

/**
 * IpcPeer implementation built around Symfony's {@see Process} component.
 */
final class SymfonyIpcPeer extends IpcPeer
{
    private const DEFAULT_SLEEP_TICK = 500;

    /**
     * @param $sleepTick ?int Delay in microseconds between polling the process output.
     */
    public function __construct(?MessageSerializer $defaultSerializer = null, ?RequestIdGenerator $idGen = null, private readonly ?int $sleepTick = null)
    {
        parent::__construct($defaultSerializer, $idGen);
    }

    /**
     * Start communicating with the given Symfony {@see Process} instance.
     */
    public function createSymfonyProcessSession(Process $process): IpcSession
    {
        return $this->createSession(
            new SymfonyProcessMessageTransport(
                $process,
                $this->defaultSerializer
            )
        );
    }

    /**
     * Register an existing SymfonyProcessMessageTransport instance.
     */
    public function createSessionFromTransport(SymfonyProcessMessageTransport $transport): IpcSession
    {
        return $this->createSession($transport);
    }

    /**
     * Poll the attached processes for output and dispatch any messages.
     */
    public function tick(?float $timeout = null): void
    {
        $start = microtime(true);
        $timeout ??= 0.0;
        $sleepTick = $this->sleepTick ?? self::DEFAULT_SLEEP_TICK;

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
