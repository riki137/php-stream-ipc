<?php declare(strict_types=1);

namespace PhpStreamIpc;

use Amp\Future;
use Amp\TimeoutCancellation;
use Amp\TimeoutException;
use PhpStreamIpc\Envelope\RequestEnvelope;
use PhpStreamIpc\Envelope\ResponseEnvelope;
use PhpStreamIpc\Envelope\Id\RequestIdGenerator;
use PhpStreamIpc\Transport\MessageCommunicator;
use PhpStreamIpc\Message\Message;
use function Amp\async;

final class SyncIpcSession
{
    /** @var (callable(Message, SyncIpcSession): void)[] */
    private array $messageHandlers = [];

    /** @var (callable(Message, SyncIpcSession): ?Message)[] */
    private array $requestHandlers = [];

    /** @var array<string,Message> */
    private array $pendingResponses = [];

    private MessageCommunicator $comm;
    private RequestIdGenerator $idGen;
    private float $timeout;

    public function __construct(
        MessageCommunicator $comm,
        RequestIdGenerator $idGen,
        float $timeout = IpcPeer::DEFAULT_TIMEOUT,
    ) {
        $this->comm = $comm;
        $this->idGen = $idGen;
        $this->timeout = $timeout;
    }

    /** One‐way notification */
    public function notify(Message $msg): void
    {
        $this->comm->send($msg);
    }

    /** Register a push‐notification handler */
    public function onMessage(callable $handler): void
    {
        $this->messageHandlers[] = $handler;
    }

    /** Register an incoming‐request handler */
    public function onRequest(callable $handler): void
    {
        $this->requestHandlers[] = $handler;
    }

    /**
     * Send a request and block until the matching ResponseEnvelope arrives
     *
     * @throws TimeoutException if no response within $this->timeout
     */
    public function request(Message $msg): Future
    {
        $id = $this->idGen->generate();
        // initialize slot
        $this->pendingResponses[$id] = null;
        // send
        $this->comm->send(new RequestEnvelope($id, $msg));
        $startNs = hrtime(true);

        return async(function() use ($startNs, $id) {
            // loop until we get it or timeout
            $timeoutNs = (int)($this->timeout * 1e9);

            while (true) {
                $elapsed = hrtime(true) - $startNs;
                if ($elapsed >= $timeoutNs) {
                    unset($this->pendingResponses[$id]);
                    throw new TimeoutException("Request timed out after {$this->timeout}s");
                }

                // tick with remaining time
                $this->tick(($timeoutNs - $elapsed) / 1e9);

                if ($this->pendingResponses[$id] instanceof Message) {
                    $response = $this->pendingResponses[$id];
                    unset($this->pendingResponses[$id]);
                    return $response;
                }
            }
        });
    }

    /**
     * Process at most one incoming envelope.
     *
     * @param float $timeout Maximum seconds to wait in this tick
     */
    public function tick(float $timeout): void
    {
        $cancellation = new TimeoutCancellation($timeout);

        try {
            $envelope = $this->comm->read($cancellation);

            if ($envelope instanceof RequestEnvelope) {
                // incoming request → call handlers & send first non-null response
                foreach ($this->requestHandlers as $h) {
                    $resp = $h($envelope->request, $this);
                    if ($resp instanceof Message) {
                        $this->comm->send(new ResponseEnvelope($envelope->id, $resp));
                        break;
                    }
                }
            } elseif ($envelope instanceof ResponseEnvelope) {
                // incoming reply → stash it
                $this->pendingResponses[$envelope->id] = $envelope->response;
            } else {
                // plain notification
                foreach ($this->messageHandlers as $h) {
                    $h($envelope, $this);
                }
            }
        } catch (TimeoutException $e) {
            // Nothing happened in this tick, let's try another time
        }
    }
}
