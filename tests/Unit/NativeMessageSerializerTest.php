<?php
declare(strict_types=1);

namespace Tests\PhpStreamIpc\Unit;

use PHPUnit\Framework\TestCase;
use PhpStreamIpc\Serialization\NativeMessageSerializer;
use PhpStreamIpc\Message\LogMessage;

final class NativeMessageSerializerTest extends TestCase
{
    public function testSerializeDeserializeRoundtrip(): void
    {
        $serializer = new NativeMessageSerializer();
        $message = new LogMessage('test', 'info');

        $payload = $serializer->serialize($message);
        $result  = $serializer->deserialize($payload);

        $this->assertInstanceOf(LogMessage::class, $result);
        $this->assertSame('test', $result->message);
        $this->assertSame('info', $result->level);
    }

    public function testDeserializeInvalidBase64ProducesErrorLog(): void
    {
        $serializer = new NativeMessageSerializer();
        $result     = $serializer->deserialize('not-base64');

        $this->assertInstanceOf(LogMessage::class, $result);
        $this->assertSame('error', $result->level);
    }

    public function testDeserializeBadSerializedDataProducesErrorLog(): void
    {
        $serializer = new NativeMessageSerializer();
        // Valid base64 but invalid serialized data
        $payload = base64_encode('garbage');
        $result  = $serializer->deserialize($payload);

        $this->assertInstanceOf(LogMessage::class, $result);
        $this->assertSame('error', $result->level);
    }
} 
