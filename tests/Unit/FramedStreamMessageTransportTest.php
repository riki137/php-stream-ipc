<?php
declare(strict_types=1);

namespace Tests\PhpStreamIpc\Unit;

use DG\BypassFinals;
use PHPUnit\Framework\TestCase;
use Amp\ByteStream\WritableResourceStream;
use Amp\ByteStream\ReadableResourceStream;
use PhpStreamIpc\Serialization\NativeMessageSerializer;
use PhpStreamIpc\Transport\FramedStreamMessageTransport;
use PhpStreamIpc\Message\LogMessage;
use PhpStreamIpc\Transport\StreamFrameReader;

final class FramedStreamMessageTransportTest extends TestCase
{

    protected function setUp(): void
    {
        BypassFinals::enable();
    }

    public function testSendFramesCorrectly(): void
    {
        $serializer = new NativeMessageSerializer();
        $message    = new LogMessage('hi', 'debug');

        // Stub a WritableResourceStream
        $write = $this->getMockBuilder(WritableResourceStream::class)
            ->disableOriginalConstructor()
            ->getMock();
        $read  = $this->getMockBuilder(ReadableResourceStream::class)
            ->disableOriginalConstructor()
            ->getMock();

        $write->expects($this->once())
            ->method('write')
            ->with($this->callback(function (string $data) use ($serializer, $message) {
                $magic  = StreamFrameReader::MAGIC;
                if (substr($data, 0, 4) !== $magic) {
                    return false;
                }
                $length  = unpack('N', substr($data, 4, 4))[1];
                $payload = substr($data, 8);
                return $length === strlen($payload)
                    && $payload === $serializer->serialize($message);
            }));

        $transport = new FramedStreamMessageTransport($write, $read, $serializer);
        $transport->send($message);
    }

    public function testSendEncodesMagicLengthAndPayload(): void
    {
        $resource   = fopen('php://temp', 'r+');
        $writeStream = new WritableResourceStream($resource);
        $readStream  = new ReadableResourceStream($resource);
        $serializer  = new NativeMessageSerializer();
        $message     = new LogMessage('xyz', 'debug');

        $transport = new FramedStreamMessageTransport(
            $writeStream,
            $readStream,
            $serializer
        );

        $transport->send($message);

        // rewind and read raw bytes
        fseek($resource, 0);
        $raw = stream_get_contents($resource);

        $magic  = StreamFrameReader::MAGIC;
        $this->assertStringStartsWith($magic, $raw, 'Frame must start with MAGIC bytes');

        $len    = unpack('N', substr($raw, 4, 4))[1];
        $payload = substr($raw, 8);

        $this->assertSame(strlen($payload), $len, 'Length prefix must match payload length');
        $this->assertSame($serializer->serialize($message), $payload, 'Payload must match serialized message');
    }
}
