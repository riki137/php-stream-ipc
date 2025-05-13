<?php
declare(strict_types=1);

namespace Tests\PhpStreamIpc\Unit;

use Amp\Cancellation;
use PhpStreamIpc\Message\Message;
use PhpStreamIpc\Transport\MessageTransport;
use PHPUnit\Framework\TestCase;
use PhpStreamIpc\IpcSession;
use PhpStreamIpc\Message\LogMessage;
use PhpStreamIpc\Envelope\Id\RequestIdGenerator;
use Tests\PhpStreamIpc\Fixtures\AutoResponseTransport;

final class IpcSessionRequestReceiveTest extends TestCase
{
    public function testRequestFutureCompletesAfterTick(): void
    {
        $transport = new AutoResponseTransport();
        $idGen     = new class implements RequestIdGenerator {
            public function generate(): string
            {
                return 'fixed-id';
            }
        };

        $session = new IpcSession($transport, $idGen, 0.1);
        $future  = $session->request(new LogMessage('ping', 'info'));

        // before tick, nothing completed
        $this->assertFalse($future->isComplete());

        // after tick, the auto-response has been read and future completed
        $session->tick();
        $this->assertTrue($future->isComplete());
    }

    public function testReceiveFutureCompletesAfterTick(): void
    {
        $raw = new LogMessage('hello', 'warn');
        $transport = new class($raw) implements MessageTransport {
            private $msg;
            public function __construct($msg) { $this->msg = $msg; }
            public function send($message): void {}
            public function read(?Cancellation $c = null): Message
            {
                return $this->msg;
            }
        };

        $session = new IpcSession($transport, $this->createStub(RequestIdGenerator::class), 0.1);
        $future  = $session->receive();

        $this->assertFalse($future->isComplete());

        $session->tick();
        $this->assertTrue($future->isComplete());
    }
}
