<?php
namespace StreamIpc\Tests\Unit;

use StreamIpc\Serialization\JsonMessageSerializer;
use StreamIpc\Message\LogMessage;
use StreamIpc\Tests\Fixtures\SimpleMessage;
use StreamIpc\Tests\Fixtures\ComplexMessage;
use PHPUnit\Framework\TestCase;

final class JsonMessageSerializerTest extends TestCase
{
    public function testRoundTripWithNestedObjects(): void
    {
        $serializer = new JsonMessageSerializer();
        $msg = new ComplexMessage(new SimpleMessage('hi'), 'secret', [1, 2]);

        $data = $serializer->serialize($msg);
        $decoded = $serializer->deserialize($data);

        $this->assertInstanceOf(ComplexMessage::class, $decoded);
        $this->assertSame('hi', $decoded->inner->text);
        $this->assertSame('secret', $decoded->getSecret());
        $this->assertSame([1, 2], $decoded->list);
    }

    public function testUnknownClassReturnsLogMessage(): void
    {
        $serializer = new JsonMessageSerializer();
        $json = json_encode(['__class' => 'NoSuchClass', 'foo' => 'bar']);
        $decoded = $serializer->deserialize($json);

        $this->assertInstanceOf(LogMessage::class, $decoded);
        $this->assertSame('error', $decoded->level);
    }
}
