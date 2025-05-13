<?php
declare(strict_types=1);

namespace PhpStreamIpc;

use Amp\Cancellation;
use Amp\CancelledException;
use Amp\CompositeCancellation;
use Amp\DeferredCancellation;
use Amp\DeferredFuture;
use Amp\Future;
use Amp\TimeoutCancellation;
use Amp\TimeoutException;
use Closure;
use PhpStreamIpc\Message\Message;
use PhpStreamIpc\Envelope\RequestEnvelope;
use PhpStreamIpc\Envelope\Id\RequestIdGenerator;
use PhpStreamIpc\Envelope\ResponseEnvelope;
use PhpStreamIpc\Transport\MessageCommunicator;
use Throwable;
use function Amp\async;
use function Amp\delay;

/**
 * Manages an IPC session lifecycle:
 * - Sends and receives Message objects asynchronously
 * - Processes Request and Response envelopes with unique IDs
 * - Supports notification and request handlers with automatic event loop ticking
 * - Enforces request timeouts and supports cancellation
 */
final class IpcSession
{
    private array $messageHandlers = [];
    private array $requestHandlers = [];
    private array $pendingResponses = [];
    private array $timeouts = [];
    private readonly DeferredCancellation $defCancellation;
    private readonly Cancellation $cancellation;
    private Future $loop;

    public function __construct(
        private readonly MessageCommunicator $comm,
        private readonly RequestIdGenerator $idGen,
        private readonly float $timeout
    ) {
        $this->defCancellation = new DeferredCancellation();
        $this->cancellation = $this->defCancellation->getCancellation();
        $this->cancellation->subscribe(function (Throwable $e): void {
            foreach ($this->pendingResponses as $d) {
                $d->error($e);
            }
            foreach ($this->timeouts as $t) {
                $t->cancel();
            }
        });
        $this->loop = async(function () {
            while (!$this->cancellation->isRequested()) {
                $this->tick($this->cancellation);
            }
        });
    }

    /**
     * Send a notification message without expecting a response.
     *
     * @param Message $msg The notification message to send.
     * @return void
     */
    public function notify(Message $msg): void
    {
        $this->comm->send($msg);
    }

    /**
     * Send a request message and return a future for the corresponding response.
     *
     * @param Message $msg The request message to send.
     * @param Cancellation|null $cancellation Optional cancellation token.
     * @return Future<Message> Future resolving to the response message.
     */
    public function request(Message $msg, ?Cancellation $cancellation = null): Future
    {
        $cancel = $cancellation !== null
            ? new CompositeCancellation($cancellation, $this->cancellation)
            : $this->cancellation;

        $id = $this->idGen->generate();
        $this->comm->send(new RequestEnvelope($id, $msg));

        $deferred = new DeferredFuture();
        $this->pendingResponses[$id] = $deferred;

        $timerCancel = new DeferredCancellation();
        $this->timeouts[$id] = $timerCancel;

        async(function () use ($id, $timerCancel, $cancel) {
            try {
                delay($this->timeout, true, $cancel);
                if (isset($this->pendingResponses[$id])) {
                    $this->pendingResponses[$id]->error(new TimeoutException('Request timed out'));
                    unset($this->pendingResponses[$id], $this->timeouts[$id]);
                }
            } catch (CancelledException $e) {
                // ignore
            }
        });

        return $deferred->getFuture();
    }

    /**
     * Receive the next incoming message as a future.
     *
     * @param Cancellation|null $cancellation Optional cancellation token.
     * @return Future<Message> Future resolving to the received message.
     */
    public function receive(?Cancellation $cancellation = null): Future
    {
        $cancel = $cancellation !== null
            ? new CompositeCancellation($cancellation, $this->cancellation)
            : $this->cancellation;

        $deferred = new DeferredFuture();

        $handler = $cancelId = null;
        $cancelId = $cancel->subscribe(function (Throwable $e) use ($deferred, $handler) {
            $this->offMessage($handler);
            if (!$deferred->isComplete()) {
                $deferred->error($e);
            }
        });
        $handler = function (Message $msg) use ($cancel, &$cancelId, $deferred, &$handler) {
            $this->offMessage($handler);
            $cancel->unsubscribe($cancelId);
            $deferred->complete($msg);
        };

        $this->onMessage($handler);

        return $deferred->getFuture();
    }

    /**
     * Register a handler for incoming notification messages.
     *
     * @param Closure(Message, IpcSession): void $handler Handler invoked on each message.
     * @return void
     */
    public function onMessage(Closure $handler): void
    {
        $this->messageHandlers[] = $handler;
    }

    /**
     * Unregister a previously registered notification handler.
     *
     * @param Closure(Message, IpcSession): void $handler Handler to remove.
     * @return void
     */
    public function offMessage(Closure $handler): void
    {
        foreach ($this->messageHandlers as $i => $h) {
            if (spl_object_id($h) === spl_object_id($handler)) {
                unset($this->messageHandlers[$i]);
                return;
            }
        }
    }

    /**
     * Register a handler for incoming request messages.
     *
     * @param Closure(Message, IpcSession): Message|null $handler Handler that processes a request and optionally returns a response.
     * @return void
     */
    public function onRequest(Closure $handler): void
    {
        $this->requestHandlers[] = $handler;
    }

    /**
     * Unregister a previously registered request handler.
     *
     * @param Closure(Message, IpcSession): Message|null $handler Handler to remove.
     * @return void
     */
    public function offRequest(Closure $handler): void
    {
        foreach ($this->requestHandlers as $i => $h) {
            if (spl_object_id($h) === spl_object_id($handler)) {
                unset($this->requestHandlers[$i]);
                return;
            }
        }
    }

    /**
     * Read and process a single incoming envelope, dispatching to the appropriate handlers.
     *
     * @param Cancellation|null $cancellation Optional cancellation token for this tick.
     * @return void
     */
    public function tick(?Cancellation $cancellation = null): void
    {
        $cancel = $cancellation !== null
            ? new CompositeCancellation($cancellation, $this->cancellation)
            : $this->cancellation;

        $envelope = $this->comm->read($cancel);

        if ($envelope instanceof RequestEnvelope) {
            foreach ($this->requestHandlers as $h) {
                $resp = $h($envelope->request, $this);
                if ($resp instanceof Message) {
                    $this->comm->send(new ResponseEnvelope($envelope->id, $resp));
                    break;
                }
            }
            return;
        }

        if ($envelope instanceof ResponseEnvelope) {
            $id = $envelope->id;
            if (isset($this->pendingResponses[$id])) {
                $this->pendingResponses[$id]->complete($envelope->response);
                $this->timeouts[$id]->cancel();
                unset($this->pendingResponses[$id], $this->timeouts[$id]);
            }
            return;
        }

        foreach ($this->messageHandlers as $h) {
            $h($envelope, $this);
        }
    }

    /**
     * Close the session, cancelling all pending operations and stopping the processing loop.
     *
     * @return void
     */
    public function close(): void
    {
        try {
            $this->defCancellation->cancel();
            $this->loop->await();
        } catch (CancelledException $e) {
            // ignore
        }
    }

}
