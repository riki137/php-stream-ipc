<?php
declare(strict_types=1);

namespace Tests\PhpStreamIpc\Unit;

use PhpStreamIpc\Envelope\ResponseEnvelope;
use PhpStreamIpc\Message\LogMessage;
use PhpStreamIpc\Serialization\JsonMessageSerializer;
use PHPUnit\Framework\TestCase;
use Tests\PhpStreamIpc\Fixtures\CustomPayload;

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

    public function testSerializeDeserializeNestedEnvelope(): void
    {
        $serializer = new JsonMessageSerializer();
        $nested     = new ResponseEnvelope('id42', new LogMessage('deep', 'warn'));

        $payload = $serializer->serialize($nested);
        $result  = $serializer->deserialize($payload);

        $this->assertInstanceOf(ResponseEnvelope::class, $result);
        $this->assertSame('id42', $result->id);
        $this->assertInstanceOf(LogMessage::class, $result->response);
        $this->assertSame('deep', $result->response->message);
        $this->assertSame('warn', $result->response->level);
    }

    public function testSerializeDeserializeCustomMessageWithNestedArray(): void
    {
        $serializer = new JsonMessageSerializer();

        $custom          = new CustomPayload();
        $custom->items   = ['one', new LogMessage('two', 'info')];

        $payload = $serializer->serialize($custom);
        $result  = $serializer->deserialize($payload);

        $this->assertInstanceOf(CustomPayload::class, $result);
        $this->assertSame(['one', 'two'], [$result->items[0], $result->items[1]->message]);
        $this->assertInstanceOf(LogMessage::class, $result->items[1]);
        $this->assertSame('info', $result->items[1]->level);
    }
} 
