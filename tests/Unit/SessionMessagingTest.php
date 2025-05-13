<?php
declare(strict_types=1);

namespace Tests\PhpStreamIpc\Unit;

use PHPUnit\Framework\TestCase;
use PhpStreamIpc\IpcSession;
use PhpStreamIpc\Message\LogMessage;
use PhpStreamIpc\Envelope\RequestEnvelope;
use PhpStreamIpc\Envelope\ResponseEnvelope;
use PhpStreamIpc\Serialization\NativeMessageSerializer;
use PhpStreamIpc\Transport\MessageCommunicator;
use PhpStreamIpc\Transport\DataReader;
use PhpStreamIpc\Transport\DataSender;
use PhpStreamIpc\Envelope\Id\RequestIdGenerator;
use PhpStreamIpc\Cancellation;
use Amp\Cancellation as AmpCancellation;

final class SessionMessagingTest extends TestCase
{
    public function testOnMessageAndTickInvokesHandler(): void
    {
        $message    = new LogMessage('foo', 'info');
        $serializer = new NativeMessageSerializer();
        $payload    = $serializer->serialize($message);

        $reader = new class($payload) implements DataReader {
            private string $payload;
            private bool $done = false;
            public function __construct(string $payload) { $this->payload = $payload; }
            public function read(?AmpCancellation $cancellation = null): string {
                if ($this->done) {
                    throw new \RuntimeException('No more data');
                }
                $this->done = true;
                return $this->payload;
            }
        };
        $sender = new class implements DataSender {
            public function send(string $message): void {}
        };

        $comm  = new MessageCommunicator($reader, $sender, $serializer);
        $idGen = $this->createMock(RequestIdGenerator::class);
        $session = new IpcSession($comm, $idGen, 1.0);

        $received = null;
        $session->onMessage(function (LogMessage $msg) use (&$received) {
            $received = $msg;
        });

        $session->tick();

        $this->assertInstanceOf(LogMessage::class, $received);
        $this->assertSame('foo', $received->message);
    }

    public function testOnRequestSendsResponse(): void
    {
        $request    = new LogMessage('bar', 'info');
        $reqId      = 'req123';
        $serializer = new NativeMessageSerializer();
        $reqEnvelope = new RequestEnvelope($reqId, $request);
        $payload     = $serializer->serialize($reqEnvelope);

        $reader = new class($payload) implements DataReader {
            private string $payload;
            private bool $done = false;
            public function __construct(string $payload) { $this->payload = $payload; }
            public function read(?AmpCancellation $cancellation = null): string {
                if ($this->done) {
                    throw new \RuntimeException('No more data');
                }
                $this->done = true;
                return $this->payload;
            }
        };

        $sender = new class implements DataSender {
            public array $messages = [];
            public function send(string $message): void {
                $this->messages[] = $message;
            }
        };

        $comm    = new MessageCommunicator($reader, $sender, $serializer);
        $idGen   = $this->createMock(RequestIdGenerator::class);
        $session = new IpcSession($comm, $idGen, 1.0);

        $session->onRequest(function (LogMessage $msg) {
            return new LogMessage($msg->message . '-resp', $msg->level);
        });

        $session->tick();

        $this->assertCount(1, $sender->messages);
        $respEnvelope = $serializer->deserialize($sender->messages[0]);

        $this->assertInstanceOf(ResponseEnvelope::class, $respEnvelope);
        $this->assertSame($reqId, $respEnvelope->id);
        $this->assertInstanceOf(LogMessage::class, $respEnvelope->response);
        $this->assertSame('bar-resp', $respEnvelope->response->message);
    }
} 
