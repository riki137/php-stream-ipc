<?php
declare(strict_types=1);

namespace Tests\PhpStreamIpc\Unit;

use PHPUnit\Framework\TestCase;
use PhpStreamIpc\IpcPeer;
use PhpStreamIpc\IpcSession;
use PhpStreamIpc\Message\LogMessage;
use PhpStreamIpc\Transport\MessageTransport;
use Amp\Cancellation;

class IpcPeerTest extends TestCase
{
    public function testBroadcastSendsMessageToAllSessions(): void
    {
        // Create a mock MessageTransport
        $transport = $this->createMock(MessageTransport::class);

        // Expect the send method to be called three times (once per session)
        $transport->expects($this->exactly(3))
            ->method('send');

        $peer = new IpcPeer();

        // Create multiple sessions
        $session1 = $peer->createSession($transport);
        $session2 = $peer->createSession($transport);
        $session3 = $peer->createSession($transport);

        // Broadcast a message to all sessions
        $broadcast = new LogMessage('broadcast-test', 'notice');
        $peer->broadcast($broadcast);
    }
}
