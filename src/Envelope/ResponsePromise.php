<?php

declare(strict_types=1);

namespace StreamIpc\Envelope;

use StreamIpc\IpcPeer;
use StreamIpc\IpcSession;
use StreamIpc\Message\ErrorMessage;
use StreamIpc\Message\ErrorMessageException;
use StreamIpc\Message\Message;
use StreamIpc\Transport\TimeoutException;

/**
 * Represents a pending IPC message that is expected to receive a response.
 * This class allows awaiting the response, handling potential timeouts.
 */
final class ResponsePromise
{
    public const DEFAULT_TIMEOUT = 30.0;

    /** @var ?Message The response message, null if not yet received. */
    private ?Message $response = null;

    /** @var float The timestamp when the promise was created, used for timeout calculation. */
    private float $start;

    /**
     * @param IpcPeer    $peer    Peer instance managing the communication.
     * @param IpcSession $session IpcSession associated with this promise.
     * @param string     $id      Identifier of the request this promise is for.
     * @param float      $timeout Timeout in seconds for awaiting the response.
     *                            Defaults to {@see DEFAULT_TIMEOUT}.
     * @internal
     */
    public function __construct(
        private readonly IpcPeer $peer,
        private readonly IpcSession $session,
        private readonly string $id,
        private readonly float $timeout = self::DEFAULT_TIMEOUT
    ) {
        $this->start = microtime(true);
        $this->session->registerPromise($this->id);
    }

    /**
     * Awaits the response message.
     * This method blocks until the response is received or a timeout occurs.
     *
     * @throws TimeoutException If the request times out before a response is received.
     * @throws ErrorMessageException If the response is an error message.
     */
    public function await(): Message
    {
        $this->response ??= $this->session->popResponse($this->id);

        while ($this->response === null) {
            $elapsed = microtime(true) - $this->start;

            $remaining = $this->timeout - $elapsed;
            if ($remaining <= 0) {
                throw new TimeoutException("IPC request timed out after {$this->timeout}s");
            }

            // now pass the remaining time (or null) into tick()
            $this->peer->tick($remaining);
            $this->response = $this->session->popResponse($this->id);
        }

        $resp = $this->response;
        $this->session->cleanupPromise($this->id);

        if ($this->response instanceof ErrorMessage) {
            throw new ErrorMessageException($this->response);
        }

        return $resp;
    }

    public function __destruct()
    {
        $this->session->cleanupPromise($this->id);
    }
}
