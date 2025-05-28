<?php
namespace StreamIpc\Tests\Unit;

use StreamIpc\IpcPeer;
use StreamIpc\Tests\Fixtures\FakeTransport;
use StreamIpc\Tests\Fixtures\SimpleMessage;
use StreamIpc\Message\LogMessage;
use StreamIpc\Envelope\RequestEnvelope;
use StreamIpc\Envelope\ResponseEnvelope;
use PHPUnit\Framework\TestCase;

class TestPeer extends IpcPeer
{
    public function createFakeSession(FakeTransport $transport): \StreamIpc\IpcSession
    {
        return $this->createSession($transport);
    }

    public function tick(?float $timeout = null): void
    {
        // no-op for tests
    }
}

final class IpcSessionTest extends TestCase
{
    public function testDispatchRequestSendsResponse(): void
    {
        $peer = new TestPeer();
        $transport = new FakeTransport();
        $session = $peer->createFakeSession($transport);

        $session->onRequest(function (SimpleMessage $msg): LogMessage {
            return new LogMessage($msg->text . '_resp');
        });

        $env = new RequestEnvelope('1', new SimpleMessage('hi'));
        $session->dispatch($env);

        $this->assertCount(1, $transport->sent);
        $this->assertInstanceOf(ResponseEnvelope::class, $transport->sent[0]);
        $this->assertSame('1', $transport->sent[0]->id);
        $this->assertSame('hi_resp', $transport->sent[0]->response->message);
    }

    public function testDispatchResponseStoresMessage(): void
    {
        $peer = new TestPeer();
        $transport = new FakeTransport();
        $session = $peer->createFakeSession($transport);

        $resp = new ResponseEnvelope('id', new SimpleMessage('ok'));
        $session->dispatch($resp);
        $this->assertSame($resp->response, $session->popResponse('id'));
    }

    public function testDispatchErrorFromHandlerSendsLogMessage(): void
    {
        $peer = new TestPeer();
        $transport = new FakeTransport();
        $session = $peer->createFakeSession($transport);

        $session->onRequest(function () {
            throw new \RuntimeException('boom');
        });

        $session->dispatch(new RequestEnvelope('123', new SimpleMessage('test')));

        $this->assertCount(1, $transport->sent);
        $this->assertInstanceOf(LogMessage::class, $transport->sent[0]);
        $this->assertStringContainsString('boom', $transport->sent[0]->message);
    }

    public function testRequestCreatesPromiseAndSendsEnvelope(): void
    {
        $peer = new TestPeer();
        $transport = new FakeTransport();
        $session = $peer->createFakeSession($transport);

        $promise = $session->request(new SimpleMessage('req'));
        $this->assertCount(1, $transport->sent);
        $this->assertInstanceOf(RequestEnvelope::class, $transport->sent[0]);
        $id = $transport->sent[0]->id;

        // respond before awaiting
        $session->dispatch(new ResponseEnvelope($id, new SimpleMessage('resp')));
        $resp = $promise->await();
        $this->assertInstanceOf(SimpleMessage::class, $resp);
        $this->assertSame('resp', $resp->text);
    }

    public function testOffMessageRemovesHandler(): void
    {
        $peer = new TestPeer();
        $transport = new FakeTransport();
        $session = $peer->createFakeSession($transport);

        $count = 0;
        $h1 = function () use (&$count) { $count++; };
        $h2 = function () use (&$count) { $count++; };
        $session->onMessage($h1);
        $session->onMessage($h2);
        $session->offMessage($h1);

        $session->dispatch(new SimpleMessage('x'));

        $this->assertSame(1, $count);
    }

    public function testOffRequestRemovesHandler(): void
    {
        $peer = new TestPeer();
        $transport = new FakeTransport();
        $session = $peer->createFakeSession($transport);

        $h1 = function () { return new LogMessage('one'); };
        $h2 = function () { return new LogMessage('two'); };
        $session->onRequest($h1);
        $session->onRequest($h2);
        $session->offRequest($h1);

        $session->dispatch(new RequestEnvelope('id', new SimpleMessage('q')));

        $this->assertInstanceOf(ResponseEnvelope::class, $transport->sent[0]);
        $this->assertSame('two', $transport->sent[0]->response->message);
    }

    public function testCloseRemovesSessionFromPeer(): void
    {
        $peer = new TestPeer();
        $transport = new FakeTransport();
        $session = $peer->createFakeSession($transport);
        $session->close();

        $peer->tick();

        $this->assertSame([], $transport->readCalls);
    }

    public function testDispatchThrowsOnMessageHandlerError(): void
    {
        $peer = new TestPeer();
        $transport = new FakeTransport();
        $session = $peer->createFakeSession($transport);

        $session->onMessage(function () {
            throw new \RuntimeException('fail');
        });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('fail');
        $session->dispatch(new SimpleMessage('hi'));
    }

    public function testNotifySendsMessage(): void
    {
        $peer = new TestPeer();
        $transport = new FakeTransport();
        $session = $peer->createFakeSession($transport);

        $msg = new SimpleMessage('notice');
        $session->notify($msg);

        $this->assertCount(1, $transport->sent);
        $this->assertSame($msg, $transport->sent[0]);
    }
}
