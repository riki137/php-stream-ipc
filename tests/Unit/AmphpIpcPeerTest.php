<?php
namespace StreamIpc\Tests\Unit;

use Amp\ByteStream\ReadableResourceStream;
use Amp\ByteStream\WritableResourceStream;
use PHPUnit\Framework\TestCase;
use StreamIpc\AmphpIpcPeer;
use StreamIpc\IpcSession;
use StreamIpc\Serialization\NativeMessageSerializer;
use StreamIpc\Tests\Fixtures\SimpleMessage;
use StreamIpc\Transport\AmpByteStreamMessageTransport;
use StreamIpc\Transport\FrameCodec;

final class AmphpIpcPeerTest extends TestCase
{
    private function createPair(): array
    {
        return stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
    }

    public function testCreateByteStreamSession(): void
    {
        [$r1, $w1] = $this->createPair();
        $peer = new AmphpIpcPeer();
        $session = $peer->createByteStreamSession(
            new WritableResourceStream($w1),
            [new ReadableResourceStream($r1)]
        );

        $this->assertInstanceOf(IpcSession::class, $session);
        $this->assertInstanceOf(AmpByteStreamMessageTransport::class, $session->getTransport());
    }

    public function testCreateSessionFromTransport(): void
    {
        [$r1, $w1] = $this->createPair();
        $transport = new AmpByteStreamMessageTransport(
            new WritableResourceStream($w1),
            [new ReadableResourceStream($r1)],
            new NativeMessageSerializer()
        );
        $peer = new AmphpIpcPeer();
        $session = $peer->createSessionFromTransport($transport);
        $this->assertSame($transport, $session->getTransport());
    }

    public function testTickDispatchesMessages(): void
    {
        [$readSock, $clientWrite] = $this->createPair();
        [$clientRead, $writeSock] = $this->createPair();

        $transport = new AmpByteStreamMessageTransport(
            new WritableResourceStream($writeSock),
            [new ReadableResourceStream($readSock)],
            new NativeMessageSerializer()
        );

        $peer = new AmphpIpcPeer();
        $session = $peer->createSessionFromTransport($transport);

        $received = [];
        $session->onMessage(function (SimpleMessage $msg) use (&$received) {
            $received[] = $msg->text;
        });

        $codec = new FrameCodec(new NativeMessageSerializer());
        \Revolt\EventLoop::queue(function () use ($clientWrite, $codec) {
            fwrite($clientWrite, $codec->pack(new SimpleMessage('hello')));
        });

        $peer->tick();

        $this->assertSame(['hello'], $received);
    }
}
