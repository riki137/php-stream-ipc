<?php
declare(strict_types=1);

namespace Tests\PhpStreamIpc\Unit;

use PHPUnit\Framework\TestCase;
use PhpStreamIpc\IpcSession;
use PhpStreamIpc\Envelope\RequestEnvelope;
use PhpStreamIpc\Envelope\ResponseEnvelope;
use PhpStreamIpc\Message\LogMessage;
use PhpStreamIpc\Envelope\Id\RequestIdGenerator;
use PhpStreamIpc\Transport\MessageTransport;
use Amp\Cancellation;
use Amp\CancelledException;

final class IpcSessionTest extends TestCase
{
    public function testNotifyCallsTransportSend(): void
    {
        $transport = $this->createMock(MessageTransport::class);
        $transport
            ->expects($this->once())
            ->method('send')
            ->with($this->isInstanceOf(LogMessage::class));

        $session = new IpcSession($transport, $this->dummyIdGen(), 0.1);
        $session->notify(new LogMessage('ping', 'info'));
    }

    public function testTickDispatchesRequestEnvelopeAndSendsResponse(): void
    {
        $incoming = new RequestEnvelope('xyz', new LogMessage('hello', 'info'));

        $transport = $this->createMock(MessageTransport::class);
        // First call to read() returns our RequestEnvelope
        $transport
            ->expects($this->once())
            ->method('read')
            ->willReturn($incoming);

        // Expect send() to be called exactly once with a ResponseEnvelope
        $transport
            ->expects($this->once())
            ->method('send')
            ->with($this->callback(function ($env) {
                return $env instanceof ResponseEnvelope
                    && $env->id === 'xyz'
                    && $env->response instanceof LogMessage
                    && $env->response->message === 'HELLO';
            }));

        $session = new IpcSession($transport, $this->dummyIdGen(), 0.1);
        $session->onRequest(function (LogMessage $msg) {
            // uppercase the message
            return new LogMessage(strtoupper($msg->message), $msg->level);
        });

        $session->tick(); // should dispatch and send back uppercase
    }

    private function dummyIdGen(): RequestIdGenerator
    {
        return new class implements RequestIdGenerator {
            public function generate(): string
            {
                return 'unused';
            }
        };
    }
}
