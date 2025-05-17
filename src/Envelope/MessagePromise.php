<?php

declare(strict_types=1);

namespace PhpStreamIpc\Envelope;

use PhpStreamIpc\IpcPeer;
use PhpStreamIpc\IpcSession;
use PhpStreamIpc\Message\Message;
use PhpStreamIpc\Transport\TimeoutException;

final class MessagePromise
{
    private ?Message $response = null;
    private float $start;

    public function __construct(
        private readonly IpcPeer $peer,
        private readonly IpcSession $session,
        private readonly string $id,
        private readonly ?float $timeout
    )
    {
        $this->start = microtime(true);
    }

    public function await(): Message
    {
        $this->response ??= $this->session->popResponse($this->id);

        while ($this->response === null) {
            $elapsed = microtime(true) - $this->start;

            $remaining = null;
            if ($this->timeout !== null) {
                $remaining = $this->timeout - $elapsed;
                if ($remaining <= 0) {
                    throw new TimeoutException("IPC request timed out after {$this->timeout}s");
                }
            }

            // now pass the remaining time (or null) into tick()
            $this->peer->tick($remaining);
            $this->response = $this->session->popResponse($this->id);
        }

        return $this->response;
    }
}
