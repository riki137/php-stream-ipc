<?php
declare(strict_types=1);

namespace StreamIpc;

use StreamIpc\Envelope\Id\RequestIdGenerator;
use StreamIpc\Envelope\RequestEnvelope;
use StreamIpc\Envelope\ResponseEnvelope;
use StreamIpc\Envelope\ResponsePromise;
use StreamIpc\Message\LogMessage;
use StreamIpc\Message\Message;
use StreamIpc\Transport\MessageTransport;
use StreamIpc\Transport\StreamClosedException;
use Throwable;

/**
 * Handles the lifecycle of an IPC session.
 * - Sends and receives {@see Message} objects.
 * - Supports registering handlers for notifications and requests, driven by an internal event loop.
 * - Manages request timeouts and cancellation.
 */
final class IpcSession
{
    /** @var array<int, callable(Message, IpcSession): void> Pending message handlers, numerically indexed. */
    private array $messageHandlers = [];

    /** @var array<int, callable(Message, IpcSession): (Message|null)> Pending request handlers, numerically indexed. */
    private array $requestHandlers = [];

    /** @var array<string, Message> Pending responses, indexed by request ID */
    private array $responses = [];

    /** @var array<string,bool> Active promises expecting a response */
    private array $pending = [];

    /**
     * Constructs a new IpcSession.
     *
     * @param $peer      IpcPeer Parent peer managing this session.
     * @param $transport MessageTransport Transport used for sending and receiving messages.
     * @param $idGen     RequestIdGenerator Generator for creating unique request IDs.
     */
    public function __construct(
        private readonly IpcPeer $peer,
        private readonly MessageTransport $transport,
        private readonly RequestIdGenerator $idGen
    ) {
    }

    /**
     * Gets the message transport associated with this session.
     */
    public function getTransport(): MessageTransport
    {
        return $this->transport;
    }

    /** @internal */
    public function registerPromise(string $id): void
    {
        $this->pending[$id] = true;
    }

    /** @internal */
    public function cleanupPromise(string $id): void
    {
        unset($this->pending[$id], $this->responses[$id]);
    }

    /**
     * Dispatches an incoming message to the appropriate handlers.
     * If the message is a request it is forwarded to request handlers;
     * responses are stored until collected.
     *
     * @throws Throwable If a message handler throws.
     */
    public function dispatch(Message $envelope): void
    {
        if ($envelope instanceof RequestEnvelope) {
            try {
                foreach ($this->requestHandlers as $h) {
                    $resp = $h($envelope->request, $this);
                    if ($resp instanceof Message) {
                        $this->transport->send(
                            new ResponseEnvelope($envelope->id, $resp)
                        );
                        break;
                    }
                }
            } catch (StreamClosedException) {
            } catch (Throwable $e) {
                try {
                    $this->transport->send(
                        new ResponseEnvelope($envelope->id, new LogMessage('Error in dispatch: ' . $e->getMessage(), 'error'))
                    );
                } catch (StreamClosedException) {
                    throw $e;
                }
            }
        } elseif ($envelope instanceof ResponseEnvelope) {
            if (isset($this->pending[$envelope->id])) {
                $this->responses[$envelope->id] = $envelope->response;
            }
        } else {
            $exception = null;
            try {
                foreach ($this->messageHandlers as $h) {
                    $h($envelope, $this);
                }
            } catch (Throwable $e) {
                $exception = $e;
            } finally {
                if ($exception !== null) {
                    throw $exception;
                }
            }
        }
    }

    /**
     * Sends a notification message that does not expect a response.
     */
    public function notify(Message $msg): void
    {
        $this->transport->send($msg);
    }

    /**
     * Sends a request message and returns a promise for the response.
     *
     * @param $timeout float Optional timeout in seconds. Defaults to
     *                    {@see ResponsePromise::DEFAULT_TIMEOUT}.
     */
    public function request(Message $msg, float $timeout = ResponsePromise::DEFAULT_TIMEOUT): ResponsePromise
    {
        $id = $this->idGen->generate();
        $this->transport->send(new RequestEnvelope($id, $msg));
        return new ResponsePromise($this->peer, $this, $id, $timeout);
    }

    /**
     * @internal
     */
    public function popResponse(string $id): ?Message
    {
        if (isset($this->responses[$id])) {
            $resp = $this->responses[$id];
            unset($this->responses[$id]);
            return $resp;
        }

        return null;
    }

    /**
     * Registers a handler for incoming notification messages.
     *
     * @param callable $handler Callback receiving the message and the session.
     */
    public function onMessage(callable $handler): void
    {
        $this->messageHandlers[] = $handler;
    }

    /**
     * Unregisters a previously registered message handler.
     */
    public function offMessage(callable $handler): void
    {
        foreach ($this->messageHandlers as $i => $h) {
            if ($h === $handler) {
                unset($this->messageHandlers[$i]);
                break;
            }
        }
    }

    /**
     * Registers a handler for incoming request messages.
     *
     * @param callable $handler Callback receiving the request message and the session.
     */
    public function onRequest(callable $handler): void
    {
        $this->requestHandlers[] = $handler;
    }

    /**
     * Unregisters a previously registered request handler.
     */
    public function offRequest(callable $handler): void
    {
        foreach ($this->requestHandlers as $i => $h) {
            if ($h === $handler) {
                unset($this->requestHandlers[$i]);
                break;
            }
        }
    }

    /**
     * Closes the IPC session and removes it from its peer.
     * This stops the session from receiving further messages or participating in I/O ticks.
     */
    public function close(): void
    {
        $this->peer->removeSession($this);
    }
}
