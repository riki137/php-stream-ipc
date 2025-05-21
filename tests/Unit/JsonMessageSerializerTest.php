<?php
namespace PhpStreamIpc\Tests\Unit;

use PhpStreamIpc\Serialization\JsonMessageSerializer;
use PhpStreamIpc\Message\LogMessage;
use PhpStreamIpc\Tests\Fixtures\SimpleMessage;
use PhpStreamIpc\Tests\Fixtures\ComplexMessage;
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
