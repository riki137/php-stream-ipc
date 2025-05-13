<?php
declare(strict_types=1);

namespace Tests\PhpStreamIpc\Unit;

use PHPUnit\Framework\TestCase;
use PhpStreamIpc\IpcSession;
use PhpStreamIpc\Message\LogMessage;
use PhpStreamIpc\Serialization\NativeMessageSerializer;
use PhpStreamIpc\Transport\MessageCommunicator;
use PhpStreamIpc\Transport\DataSender;
use PhpStreamIpc\Transport\DataReader;
use PhpStreamIpc\Envelope\Id\RequestIdGenerator;
use Amp\Cancellation;

final class SessionNotifyTest extends TestCase
{
    public function testNotifySendsSerializedMessage(): void
    {
        // Spy DataSender to capture payloads
        $sender = new class implements DataSender {
            public array $sent = [];
            public function send(string $message): void
            {
                $this->sent[] = $message;
            }
        };

        // Dummy DataReader, not used by notify()
        $reader = new class implements DataReader {
            public function read(?Cancellation $cancellation = null): string
            {
                return '';
            }
        };

        $serializer = new NativeMessageSerializer();
        $comm = new MessageCommunicator($reader, $sender, $serializer);
        $idGen = $this->createMock(RequestIdGenerator::class);
        $session = new IpcSession($comm, $idGen, 1.0);
        $message = new LogMessage('hello-notify', 'info');

        // Act
        $session->notify($message);

        // Assert
        $this->assertCount(1, $sender->sent, 'Expected one payload sent');
        $payload = $sender->sent[0];

        // Deserialize to verify integrity
        $received = $serializer->deserialize($payload);
        $this->assertInstanceOf(LogMessage::class, $received);
        $this->assertSame('hello-notify', $received->message);
        $this->assertSame('info', $received->level);
    }
} 
