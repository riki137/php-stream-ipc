<?php
declare(strict_types=1);

namespace Tests\PhpStreamIpc\Unit;

use PHPUnit\Framework\TestCase;
use PhpStreamIpc\Serialization\JsonMessageSerializer;
use PhpStreamIpc\Message\LogMessage;

final class JsonMessageSerializerTest extends TestCase
{
    public function testSerializeDeserializeRoundtrip(): void
    {
        $serializer = new JsonMessageSerializer();
        $message    = new LogMessage('json', 'warning');

        $payload = $serializer->serialize($message);
        $result  = $serializer->deserialize($payload);

        $this->assertInstanceOf(LogMessage::class, $result);
        $this->assertSame('json', $result->message);
        $this->assertSame('warning', $result->level);
    }

    public function testDeserializeInvalidJsonProducesErrorLog(): void
    {
        $serializer = new JsonMessageSerializer();
        $result     = $serializer->deserialize('{invalid json');

        $this->assertInstanceOf(LogMessage::class, $result);
        $this->assertSame('error', $result->level);
    }

    public function testDeserializeUnknownClassProducesErrorLog(): void
    {
        $data    = ['__class' => 'Nonexistent', 'foo' => 'bar'];
        $payload = json_encode($data);
        $serializer = new JsonMessageSerializer();

        $result = $serializer->deserialize($payload);

        $this->assertInstanceOf(LogMessage::class, $result);
        $this->assertSame('error', $result->level);
    }
} 
