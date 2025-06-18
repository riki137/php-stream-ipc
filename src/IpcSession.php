<?php
declare(strict_types=1);

namespace StreamIpc;

use StreamIpc\Envelope\Id\RequestIdGenerator;
use StreamIpc\Envelope\RequestEnvelope;
use StreamIpc\Envelope\ResponseEnvelope;
use StreamIpc\Envelope\ResponsePromise;
use StreamIpc\Message\ErrorMessage;
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
     * Create a new IPC session.
     *
     * @param IpcPeer $peer Parent peer managing this session.
     * @param MessageTransport $transport Transport used for sending and receiving messages.
     * @param RequestIdGenerator $idGen Generator for creating unique request IDs.
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
        // Handle response messages (simplest case)
        if ($envelope instanceof ResponseEnvelope) {
            if (isset($this->pending[$envelope->id])) {
                $this->responses[$envelope->id] = $envelope->response;
            }
            return;
        }

        // Handle request messages
        if ($envelope instanceof RequestEnvelope) {
            $this->handleRequest($envelope);
            return;
        }

        // Handle notification messages
        foreach ($this->messageHandlers as $handler) {
            $handler($envelope, $this);
        }
    }

    /**
     * Processes a request and sends an appropriate response.
     */
    private function handleRequest(RequestEnvelope $envelope): void
    {
        try {
            // Try to find a handler that returns a message
            foreach ($this->requestHandlers as $handler) {
                if (($response = $handler($envelope->request, $this)) instanceof Message) {
                    $this->transport->send(new ResponseEnvelope($envelope->id, $response));
                    return;
                }
            }

            // No handler found
            $this->transport->send(new ResponseEnvelope(
                $envelope->id,
                new ErrorMessage('Unhandled request')
            ));
        } catch (StreamClosedException) {
            // Can't send response if stream is closed
        } catch (Throwable $error) {
            try {
                $this->transport->send(new ResponseEnvelope(
                    $envelope->id,
                    new ErrorMessage('Error in dispatch', $error)
                ));
            } catch (StreamClosedException) {
                throw $error;
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
     * @param float $timeout Optional timeout in seconds. Defaults to
     *                       {@see ResponsePromise::DEFAULT_TIMEOUT}.
     *
     * @example
     * ```php
     * $promise = $session->request(new MyMessage());
     * $reply = $promise->await();
     * ```
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
     * @param callable(Message, IpcSession): void $handler Callback receiving the message and the session.
     */
    public function onMessage(callable $handler): void
    {
        $this->messageHandlers[] = $handler;
    }

    /**
     * Unregisters a previously registered message handler.
     *
     * @param callable(Message, IpcSession): void $handler The handler to remove.
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
     * @param callable(Message, IpcSession): void $handler Callback receiving the request message and the session.
     */
    public function onRequest(callable $handler): void
    {
        $this->requestHandlers[] = $handler;
    }

    /**
     * Unregisters a previously registered request handler.
     *
     * @param callable(Message, IpcSession): void $handler The handler to remove.
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
