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

class IpcSession
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
            while (true) {
                $this->tick($this->cancellation);
            }
        });
    }

    public function notify(Message $msg): void
    {
        $this->comm->send($msg);
    }

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
            delay($this->timeout, true, $cancel);
            if (isset($this->pendingResponses[$id])) {
                $this->pendingResponses[$id]->error(new TimeoutException('Request timed out'));
                unset($this->pendingResponses[$id], $this->timeouts[$id]);
            }
        });

        return $deferred->getFuture();
    }

    public function receive(?Cancellation $cancellation = null): Future
    {
        $cancel = $cancellation !== null
            ? new CompositeCancellation($cancellation, $this->cancellation)
            : $this->cancellation;

        $deferred = new DeferredFuture();

        $cancelId = $cancel->subscribe(function (Throwable $e) use ($deferred, $handler) {
            $this->offMessage($handler);
            if (!$deferred->isComplete()) {
                $deferred->error($e);
            }
        });
        $handler = function (Message $msg) use ($cancel, $cancelId, $deferred, &$handler) {
            $this->offMessage($handler);
            $cancel->unsubscribe($cancelId);
            $deferred->complete($msg);
        };

        $this->onMessage($handler);

        return $deferred->getFuture();
    }

    public function onMessage(Closure $handler): void
    {
        $this->messageHandlers[] = $handler;
    }

    public function offMessage(Closure $handler): void
    {
        foreach ($this->messageHandlers as $i => $h) {
            if (spl_object_id($h) === spl_object_id($handler)) {
                unset($this->messageHandlers[$i]);
                return;
            }
        }
    }

    public function onRequest(Closure $handler): void
    {
        $this->requestHandlers[] = $handler;
    }

    public function offRequest(Closure $handler): void
    {
        foreach ($this->requestHandlers as $i => $h) {
            if (spl_object_id($h) === spl_object_id($handler)) {
                unset($this->requestHandlers[$i]);
                return;
            }
        }
    }

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

    public function close(): void
    {
        $this->defCancellation->cancel();
        $this->loop->await();
    }

}
