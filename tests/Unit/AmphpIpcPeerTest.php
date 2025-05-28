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
}
