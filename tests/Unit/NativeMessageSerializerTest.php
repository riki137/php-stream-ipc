<?php
namespace PhpStreamIpc\Tests\Unit;

use PhpStreamIpc\Serialization\NativeMessageSerializer;
use PhpStreamIpc\Message\LogMessage;
use PhpStreamIpc\Tests\Fixtures\SimpleMessage;
use PHPUnit\Framework\TestCase;
use stdClass;

final class NativeMessageSerializerTest extends TestCase
{
    public function testRoundTrip(): void
    {
        $serializer = new NativeMessageSerializer();
        $msg = new SimpleMessage('ok');
        $data = $serializer->serialize($msg);
        $decoded = $serializer->deserialize($data);

        $this->assertInstanceOf(SimpleMessage::class, $decoded);
        $this->assertSame('ok', $decoded->text);
    }

    public function testInvalidDataReturnsLogMessage(): void
    {
        $serializer = new NativeMessageSerializer();
        $decoded = $serializer->deserialize('not a serialized message');
        $this->assertInstanceOf(LogMessage::class, $decoded);
        $this->assertSame('error', $decoded->level);
    }

    public function testNonMessageObjectReturnsLogMessage(): void
    {
        $serializer = new NativeMessageSerializer();
        $data = serialize(new stdClass());
        $decoded = $serializer->deserialize($data);
        $this->assertInstanceOf(LogMessage::class, $decoded);
        $this->assertSame('error', $decoded->level);
    }
}
