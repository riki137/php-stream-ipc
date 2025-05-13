<?php
declare(strict_types=1);

namespace Tests\PhpStreamIpc\Unit;

use PHPUnit\Framework\TestCase;
use PhpStreamIpc\IpcPeer;
use PhpStreamIpc\IpcSession;
use PhpStreamIpc\Message\LogMessage;
use PhpStreamIpc\Transport\DataReader;
use PhpStreamIpc\Transport\DataSender;
use Amp\Cancellation;

class IpcPeerTest extends TestCase
{
    public function testBroadcastSendsMessageToAllSessions(): void
    {
        // Create a sender spy that tracks message sending
        $sender = new class implements DataSender {
            public array $sent = [];
            public function send(string $message): void {
                $this->sent[] = $message;
            }
        };

        // Create dummy reader
        $reader = new class implements DataReader {
            public function read(?Cancellation $cancellation = null): string {
                return '';
            }
        };

        $peer = new IpcPeer();
        
        // Create multiple sessions
        $session1 = $peer->createSession($sender, $reader);
        $session2 = $peer->createSession($sender, $reader);
        $session3 = $peer->createSession($sender, $reader);
        
        // Broadcast a message to all sessions
        $broadcast = new LogMessage('broadcast-test', 'notice');
        $peer->broadcast($broadcast);
        
        // Each session should have received the message
        $this->assertCount(3, $sender->sent, 'Expected three broadcasts (one per session)');
    }
} 
