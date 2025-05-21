<?php
namespace PhpStreamIpc\Tests\Unit;

use PhpStreamIpc\Transport\StreamFrameReader;
use PhpStreamIpc\Serialization\NativeMessageSerializer;
use PhpStreamIpc\Tests\Fixtures\SimpleMessage;
use PhpStreamIpc\Message\LogMessage;
use PHPUnit\Framework\TestCase;

final class StreamFrameReaderTest extends TestCase
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
        $payload = $ser->serialize($msg);
        return StreamFrameReader::MAGIC . pack('N', strlen($payload)) . $payload;
    }

    public function testReadsSingleMessage(): void
    {
        $ser = new NativeMessageSerializer();
        $stream = $this->createStream($this->frameFor(new SimpleMessage('hi'), $ser));
        $reader = new StreamFrameReader($stream, $ser, 1024);
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
        $reader = new StreamFrameReader($stream, $ser, 1024);
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
        $frame = StreamFrameReader::MAGIC . pack('N', strlen($payload)) . $payload;
        $stream = $this->createStream($frame);
        $reader = new StreamFrameReader($stream, $ser, 1024);
        $msgs = $reader->readFrameSync();
        $this->assertCount(1, $msgs);
        $this->assertInstanceOf(LogMessage::class, $msgs[0]);
        $this->assertSame($payload, $msgs[0]->message);
    }
}
