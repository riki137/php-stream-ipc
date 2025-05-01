<?php
declare(strict_types=1);

namespace PhpStreamIpc;

use Amp\Cancellation;
use Amp\DeferredCancellation;
use Amp\DeferredFuture;
use Amp\Future;
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

final class AsyncIpcSession implements IpcSession
{
    /** @var (Closure(Message, AsyncIpcSession): void)[] */
    private array $messageHandlers = [];

    /** @var (Closure(Message, AsyncIpcSession): ?Message)[] */
    private array $requestHandlers = [];

    /** @var DeferredFuture */
    private array $pendingResponses = [];

    /** @var DeferredCancellation[] */
    private array $timeouts = [];

    public function __construct(
        private readonly MessageCommunicator $comm,
        private readonly RequestIdGenerator $idGen,
        private readonly float $timeout,
        private readonly ?Cancellation $cancellation = null,
    ) {
        async(fn() => $this->start());
        $this->cancellation?->subscribe(static function (Throwable $exception): void {
            foreach ($this->pendingResponses as $deferred) {
                $deferred->error($exception);
            }
            foreach ($this->timeouts as $cancellation) {
                $cancellation->cancel();
            }
        });
    }

    /** Send a one‐way notification to this peer */
    public function notify(Message $msg): void
    {
        $this->comm->send($msg);
    }

    /**
     * Send a request and get back a Future<Message>.
     * It will error with TimeoutException if no response arrives in time.
     */
    public function request(Message $msg): Future
    {
        $id = $this->idGen->generate();
        $envelope = new RequestEnvelope($id, $msg);
        $this->comm->send($envelope);

        $deferred = new DeferredFuture();
        $this->pendingResponses[$id] = $deferred;

        // timeout
        $this->timeouts[$id] = $timeout = new DeferredCancellation();
        async(function () use ($timeout, $id) {
            delay($this->timeout, true, $timeout->getCancellation());
            $this->pendingResponses[$id]?->error(new TimeoutException('Request timed out'));
            unset($this->pendingResponses[$id], $this->timeouts[$id]);
        });

        return $deferred->getFuture();
    }

    public function onMessage(Closure $handler): void
    {
        $this->messageHandlers[] = $handler;
    }

    public function offMessage(Closure $handler): void
    {
        foreach ($this->messageHandlers as $i => $one) {
            if (\spl_object_id($one) === \spl_object_id($handler)) {
                unset($this->messageHandlers[$i]);
                break;
            }
        }
    }

    public function onRequest(Closure $handler): void
    {
        $this->requestHandlers[] = $handler;
    }

    public function offRequest(Closure $handler): void
    {
        foreach ($this->requestHandlers as $i => $one) {
            if (\spl_object_id($one) === \spl_object_id($handler)) {
                unset($this->requestHandlers[$i]);
                break;
            }
        }
    }

    /**
     * Receive a single message from the peer.
     */
    public function receive(): Future
    {
        $deferred = new DeferredFuture();
        $handler = function (Message $msg) use ($deferred, &$handler) {
            $deferred->complete($msg);
            $this->offMessage($handler);
        };
        $this->onMessage($handler);

        return $deferred->getFuture();
    }


    private function start(): void
    {
        while (!$this->cancellation?->isRequested()) {
            $envelope = $this->comm->read();

            //–– Incoming Request?
            if ($envelope instanceof RequestEnvelope) {
                foreach ($this->requestHandlers as $handler) {
                    $resp = $handler($envelope->request, $this);
                    if ($resp instanceof Message) {
                        $this->comm->send(new ResponseEnvelope($envelope->id, $resp));
                        break;
                    }
                }
                continue;
            }

            //–– Incoming Response?
            if ($envelope instanceof ResponseEnvelope) {
                $id = $envelope->id;
                if (isset($this->pendingResponses[$id], $this->timeouts[$id])) {
                    $this->pendingResponses[$id]->complete($envelope->response);
                    $this->timeouts[$id]->cancel();
                    unset($this->pendingResponses[$id], $this->timeouts[$id]);
                }
                continue;
            }

            //–– Plain notification/push
            foreach ($this->messageHandlers as $handler) {
                $handler($envelope, $this);
            }
        }
    }
}
