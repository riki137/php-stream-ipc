<?php
namespace StreamIpc\Tests\Unit;

use StreamIpc\Transport\NativeFrameReader;
use StreamIpc\Transport\FrameCodec;
use StreamIpc\Serialization\NativeMessageSerializer;
use StreamIpc\Tests\Fixtures\SimpleMessage;
use StreamIpc\Message\LogMessage;
use PHPUnit\Framework\TestCase;

final class NativeFrameReaderTest extends TestCase
{
    private function createStream(string $data)
    {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $data);
        rewind($stream);
        return $stream;
    }

    private function frameFor(SimpleMessage $msg, NativeMessageSerializer $ser): string
    {
        $codec = new FrameCodec($ser, 1024);
        return $codec->pack($msg);
    }

    public function testReadsSingleMessage(): void
    {
        $ser = new NativeMessageSerializer();
        $stream = $this->createStream($this->frameFor(new SimpleMessage('hi'), $ser));
        $reader = new NativeFrameReader($stream, $ser, 1024);
        $msgs = $reader->readFrameSync();
        $this->assertCount(1, $msgs);
        $this->assertInstanceOf(SimpleMessage::class, $msgs[0]);
        $this->assertSame('hi', $msgs[0]->text);
    }

    public function testJunkBeforeFrameProducesLogMessage(): void
    {
        $ser = new NativeMessageSerializer();
        $frame = $this->frameFor(new SimpleMessage('ok'), $ser);
        $stream = $this->createStream('junk' . $frame);
        $reader = new NativeFrameReader($stream, $ser, 1024);
        $msgs = $reader->readFrameSync();
        $this->assertCount(2, $msgs);
        $this->assertInstanceOf(LogMessage::class, $msgs[0]);
        $this->assertSame('junk', $msgs[0]->message);
        $this->assertInstanceOf(SimpleMessage::class, $msgs[1]);
    }

    public function testInvalidPayloadReturnsLogMessage(): void
    {
        $ser = new NativeMessageSerializer();
        $payload = 'not serialized';
        $frame = FrameCodec::MAGIC . pack('N', strlen($payload)) . $payload;
        $stream = $this->createStream($frame);
        $reader = new NativeFrameReader($stream, $ser, 1024);
        $msgs = $reader->readFrameSync();
        $this->assertCount(1, $msgs);
        $this->assertInstanceOf(LogMessage::class, $msgs[0]);
        $this->assertSame($payload, $msgs[0]->message);
    }
}
