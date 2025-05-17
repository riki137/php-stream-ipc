<?php
declare(strict_types=1);

namespace PhpStreamIpc;

use PhpStreamIpc\Envelope\Id\RequestIdGenerator;
use PhpStreamIpc\Envelope\RequestEnvelope;
use PhpStreamIpc\Envelope\ResponseEnvelope;
use PhpStreamIpc\Message\LogMessage;
use PhpStreamIpc\Message\Message;
use PhpStreamIpc\Transport\MessageTransport;
use PhpStreamIpc\Transport\TimeoutException;

/**
 * Handles the lifecycle of an IPC session.
 * - Sends and receives {@see Message} objects asynchronously.
 * - Supports registering handlers for notifications and requests, driven by an internal event loop.
 * - Manages request timeouts and cancellation.
 */
final class IpcSession
{
    /** @var array<callable(Message, IpcSession): void> Pending message handlers, indexed by handler ID */
    private array $messageHandlers = [];

    /** @var array<callable(Message, IpcSession): Message|null> Pending request handlers, indexed by handler ID */
    private array $requestHandlers = [];

    /** @var array<string, Message> Pending responses, indexed by request ID */
    private array $responses = [];

    public function __construct(
        private readonly IpcPeer $peer,
        private readonly MessageTransport $transport,
        private readonly RequestIdGenerator $idGen
    ) {
    }

    public function getTransport(): MessageTransport
    {
        return $this->transport;
    }

    public function dispatch(Message $envelope): void
    {
        try {
            if ($envelope instanceof RequestEnvelope) {
                foreach ($this->requestHandlers as $h) {
                    $resp = $h($envelope->request, $this);
                    if ($resp instanceof Message) {
                        $this->transport->send(
                            new ResponseEnvelope($envelope->id, $resp)
                        );
                        break;
                    }
                }
            } elseif ($envelope instanceof ResponseEnvelope) {
                $this->responses[$envelope->id] = $envelope->response;
            } else {
                foreach ($this->messageHandlers as $h) {
                    $h($envelope, $this);
                }
            }
        } catch (\Throwable $e) {
            $this->transport->send(
                new LogMessage('Error in dispatch: ' . $e->getMessage(), 'error')
            );
        }
    }

    public function notify(Message $msg): void
    {
        $this->transport->send($msg);
    }

    public function request(Message $msg, ?float $timeout = null): Message
    {
        $id = $this->idGen->generate();
        $this->transport->send(new RequestEnvelope($id, $msg));

        $start = microtime(true);
        while (!isset($this->responses[$id])) {
            $elapsed = microtime(true) - $start;

            if ($timeout !== null) {
                $remaining = $timeout - $elapsed;
                if ($remaining <= 0) {
                    throw new TimeoutException("IPC request timed out after {$timeout}s");
                }
            } else {
                $remaining = null; // block indefinitely if no timeout was given
            }

            // now pass the remaining time (or null) into tick()
            $this->peer->tick($remaining);
        }

        $resp = $this->responses[$id];
        unset($this->responses[$id]);
        return $resp;
    }

    public function onMessage(callable $handler): void
    {
        $this->messageHandlers[] = $handler;
    }

    public function offMessage(callable $handler): void
    {
        foreach ($this->messageHandlers as $i => $h) {
            if ($h === $handler) {
                unset($this->messageHandlers[$i]);
                break;
            }
        }
    }

    public function onRequest(callable $handler): void
    {
        $this->requestHandlers[] = $handler;
    }

    public function offRequest(callable $handler): void
    {
        foreach ($this->requestHandlers as $i => $h) {
            if ($h === $handler) {
                unset($this->requestHandlers[$i]);
                break;
            }
        }
    }

    public function close(): void
    {
        $this->peer->removeSession($this);
    }
}
