<?php
namespace StreamIpc\Tests\Unit;

use StreamIpc\IpcPeer;
use StreamIpc\Tests\Fixtures\FakeStreamTransport;
use StreamIpc\IpcSession;
use PHPUnit\Framework\TestCase;

class TestStreamPeer extends IpcPeer
{
    public function add(FakeStreamTransport $t): IpcSession
    {
        return $this->createSession($t);
    }

    public function tick(?float $timeout = null): void
    {
        foreach ($this->sessions as $s) {
            $t = $s->getTransport();
            foreach ($t->getReadStreams() as $stream) {
                $t->readFromStream($stream);
            }
        }
    }
}

final class NativeIpcPeerTest extends TestCase
{
    public function testTickForLoopsUntilTimeout(): void
    {
        $peer = new TestStreamPeer();
        $t = new FakeStreamTransport();
        $peer->add($t);

        $peer->tickFor(0.02);
        $this->assertNotEmpty($t->tickArgs);
        $count = count($t->tickArgs);

        $peer->tickFor(0.0);
        $this->assertCount($count, $t->tickArgs);
    }

    public function testClosedSessionNoLongerTicked(): void
    {
        $peer = new TestStreamPeer();
        $t = new FakeStreamTransport();
        $session = $peer->add($t);

        $session->close();
        $peer->tick();

        $this->assertSame([], $t->tickArgs);
    }
}
