<?php
namespace StreamIpc\Tests\Unit;

use Amp\ByteStream\ReadableResourceStream;
use Amp\ByteStream\WritableResourceStream;
use StreamIpc\AmphpIpcPeer;
use StreamIpc\Serialization\NativeMessageSerializer;
use StreamIpc\Tests\Fixtures\SimpleMessage;
use StreamIpc\Transport\AmpByteStreamMessageTransport;
use StreamIpc\Transport\FrameCodec;
use PHPUnit\Framework\TestCase;

final class AmpByteStreamMessageTransportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (extension_loaded('xdebug') && ini_get('xdebug.mode') === 'coverage') {
            $this->markTestIncomplete('Skipping test due to Xdebug coverage mode');
        }
    }

    private function createPair(): array
    {
        return stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
    }

    public function testSendWritesFrame(): void
    {

        [$clientRead, $transportWriteSock] = $this->createPair();
        [$transportReadSock, $clientWrite] = $this->createPair();

        $transport = new AmpByteStreamMessageTransport(
            new WritableResourceStream($transportWriteSock),
            [new ReadableResourceStream($transportReadSock)],
            new NativeMessageSerializer()
        );

        $message = new SimpleMessage('hello');
        $transport->send($message);

        $data = fread($clientRead, 8192);
        $codec = new FrameCodec(new NativeMessageSerializer());
        $msgs = $codec->feed($data);

        $this->assertCount(1, $msgs);
        $this->assertInstanceOf(SimpleMessage::class, $msgs[0]);
        $this->assertSame('hello', $msgs[0]->text);
    }

    public function testTickDispatchesIncomingMessage(): void
    {
        [$transportReadSock, $clientWrite] = $this->createPair();
        [$transportWriteSock, $clientRead] = $this->createPair();

        $transport = new AmpByteStreamMessageTransport(
            new WritableResourceStream($transportWriteSock),
            [new ReadableResourceStream($transportReadSock)],
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
            fwrite($clientWrite, $codec->pack(new SimpleMessage('ping')));
        });

        $peer->tick();

        $this->assertSame(['ping'], $received);
    }

}
