<?php
declare(strict_types=1);

namespace PhpStreamIpc;

use PhpStreamIpc\Envelope\Id\RequestIdGenerator;
use PhpStreamIpc\Envelope\ResponsePromise;
use PhpStreamIpc\Envelope\RequestEnvelope;
use PhpStreamIpc\Envelope\ResponseEnvelope;
use PhpStreamIpc\Message\LogMessage;
use PhpStreamIpc\Message\Message;
use PhpStreamIpc\Transport\MessageTransport;
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

    /**
     * Constructs a new IpcSession.
     *
     * @param IpcPeer $peer The parent peer managing this session.
     * @param MessageTransport $transport The transport layer for sending/receiving messages.
     * @param RequestIdGenerator $idGen The generator for creating unique request IDs.
     */
    public function __construct(
        private readonly IpcPeer $peer,
        private readonly MessageTransport $transport,
        private readonly RequestIdGenerator $idGen
    ) {
    }

    /**
     * Gets the message transport associated with this session.
     *
     * @return MessageTransport The message transport instance.
     */
    public function getTransport(): MessageTransport
    {
        return $this->transport;
    }

    /**
     * Dispatches an incoming message to the appropriate handlers.
     * If the message is a request, it's passed to request handlers.
     * If it's a response, it's stored for a pending request.
     * Otherwise, it's passed to general message handlers.
     *
     * @param Message $envelope The incoming message (can be a RequestEnvelope, ResponseEnvelope, or other Message).
     * @throws Throwable (anything thrown by an onMessage handler)
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
            } catch (Throwable $e) {
                $this->transport->send(
                    new LogMessage('Error in dispatch: ' . $e->getMessage(), 'error')
                );
            }
        } elseif ($envelope instanceof ResponseEnvelope) {
            $this->responses[$envelope->id] = $envelope->response;
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
     * Sends a notification message (a message that doesn't expect a direct response).
     *
     * @param Message $msg The message to send.
     */
    public function notify(Message $msg): void
    {
        $this->transport->send($msg);
    }

    /**
     * Sends a request message and waits for a response.
     *
     * @param Message $msg The request message to send.
     * @param float|null $timeout Optional timeout in seconds. If null, waits indefinitely.
     * @return ResponsePromise The response message promise.
     */
    public function request(Message $msg, ?float $timeout = null): ResponsePromise
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
     * Registers a handler for incoming notification messages (messages that are not requests or responses).
     *
     * The handler callable should accept two arguments:
     * - `Message $message`: The received message.
     * - `IpcSession $session`: The current IPC session instance.
     * It should return `void`.
     *
     * @param callable(Message, IpcSession): void $handler The callback to execute when a message is received.
     */
    public function onMessage(callable $handler): void
    {
        $this->messageHandlers[] = $handler;
    }

    /**
     * Unregisters a previously registered message handler.
     *
     * @param callable $handler The handler to remove. Must be the same instance as was registered.
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
     * The handler callable should accept two arguments:
     * - `Message $request`: The received request message.
     * - `IpcSession $session`: The current IPC session instance.
     * It should return a `Message` object as the response, or `null` if this handler doesn't process the request.
     *
     * @param callable(Message, IpcSession): (Message|null) $handler The callback to execute when a request is received.
     */
    public function onRequest(callable $handler): void
    {
        $this->requestHandlers[] = $handler;
    }

    /**
     * Unregisters a previously registered request handler.
     *
     * @param callable $handler The handler to remove. Must be the same instance as was registered.
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
