<?php
declare(strict_types=1);

namespace Tests\PhpStreamIpc\Unit;

use PHPUnit\Framework\TestCase;
use PhpStreamIpc\Transport\MessageCommunicator;
use PhpStreamIpc\Transport\DataSender;
use PhpStreamIpc\Transport\DataReader;
use PhpStreamIpc\Serialization\NativeMessageSerializer;
use PhpStreamIpc\Message\LogMessage;
use Amp\Cancellation;

final class MessageCommunicatorTest extends TestCase
{
    public function testSendSerializesAndSends(): void
    {
        $sender = new class implements DataSender {
            public string $lastPayload;
            public function send(string $message): void { $this->lastPayload = $message; }
        };
        $reader = new class implements DataReader {
            public function read(?Cancellation $cancellation = null): string { return ''; }
        };
        $serializer = new NativeMessageSerializer();
        $comm = new MessageCommunicator($reader, $sender, $serializer);

        $message = new LogMessage('comm-test','debug');
        $comm->send($message);

        $this->assertSame(
            $serializer->serialize($message),
            $sender->lastPayload
        );
    }

    public function testReadDeserializesIncoming(): void
    {
        $serializer = new NativeMessageSerializer();
        $message = new LogMessage('read-test','info');
        $payload = $serializer->serialize($message);

        $reader = new class($payload) implements DataReader {
            private string $payload;
            public function __construct(string $payload) { $this->payload = $payload; }
            public function read(?Cancellation $cancellation = null): string { return $this->payload; }
        };
        $sender = new class implements DataSender {
            public function send(string $message): void {}
        };

        $comm = new MessageCommunicator($reader, $sender, $serializer);
        $result = $comm->read();

        $this->assertInstanceOf(LogMessage::class,$result);
        $this->assertSame('read-test',$result->message);
        $this->assertSame('info',$result->level);
    }

    public function testUsesDefaultSerializerWhenNoneProvided(): void
    {
        $reader = new class implements DataReader {
            public function read(?Cancellation $cancellation = null): string { return ''; }
        };
        $sender = new class implements DataSender {
            public string $sent;
            public function send(string $message): void { $this->sent = $message; }
        };
        // No serializer passed, should use NativeMessageSerializer
        $comm = new MessageCommunicator($reader, $sender);

        $message = new LogMessage('default','info');
        $comm->send($message);
        $this->assertStringNotContainsString("\n", $sender->sent);
    }
} 
