<?php

declare(strict_types=1);

namespace StreamIpc\Envelope;

use StreamIpc\IpcPeer;
use StreamIpc\IpcSession;
use StreamIpc\Message\Message;
use StreamIpc\Transport\TimeoutException;

/**
 * Represents a pending IPC message that is expected to receive a response.
 * This class allows awaiting the response, handling potential timeouts.
 */
final class ResponsePromise
{
    /** @var ?Message The response message, null if not yet received. */
    private ?Message $response = null;

    /** @var float The timestamp when the promise was created, used for timeout calculation. */
    private float $start;

    /**
     * @param $peer    IpcPeer Peer instance managing the communication.
     * @param $session  IpcSession associated with this promise.
     * @param $id       string Identifier of the request this promise is for.
     * @param $timeout  ?float Timeout in seconds for awaiting the response, null for none.
     * @internal
     */
    public function __construct(
        private readonly IpcPeer $peer,
        private readonly IpcSession $session,
        private readonly string $id,
        private readonly ?float $timeout
    ) {
        $this->start = microtime(true);
        $this->session->registerPromise($this->id);
    }

    /**
     * Awaits the response message.
     * This method blocks until the response is received or a timeout occurs.
     *
     * @throws TimeoutException If the request times out before a response is received.
     */
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

        $resp = $this->response;
        $this->session->cleanupPromise($this->id);

        return $resp;
    }

    public function __destruct()
    {
        $this->session->cleanupPromise($this->id);
    }
}
