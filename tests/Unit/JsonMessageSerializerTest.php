<?php
namespace StreamIpc\Tests\Unit;

use StreamIpc\Serialization\JsonMessageSerializer;
use StreamIpc\Message\ErrorMessage;
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

    public function testUnknownClassReturnsErrorMessage(): void
    {
        $serializer = new JsonMessageSerializer();
        $json = json_encode(['__class' => 'NoSuchClass', 'foo' => 'bar']);
        $decoded = $serializer->deserialize($json);

        $this->assertInstanceOf(ErrorMessage::class, $decoded);
        $this->assertStringContainsString('Unknown class', $decoded->toString());
    }

    public function testInvalidJsonProducesErrorMessage(): void
    {
        $serializer = new JsonMessageSerializer();
        $decoded = $serializer->deserialize('{invalid json');
        $this->assertInstanceOf(ErrorMessage::class, $decoded);
        $this->assertStringContainsString('json_decode failed', $decoded->toString());
    }

    public function testDeserializedObjectNotImplementingMessage(): void
    {
        $serializer = new JsonMessageSerializer();
        $objData = json_encode(['__class' => \stdClass::class]);
        $decoded = $serializer->deserialize($objData);
        $this->assertInstanceOf(ErrorMessage::class, $decoded);
        $this->assertStringContainsString('Decoded object is not a Message', $decoded->toString());
    }
}
