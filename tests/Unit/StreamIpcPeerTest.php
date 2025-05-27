<?php
namespace PhpStreamIpc\Tests\Unit;

use PhpStreamIpc\IpcPeer;
use PhpStreamIpc\Tests\Fixtures\FakeStreamTransport;
use PhpStreamIpc\IpcSession;
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
            $s->getTransport()->tick([$s], $timeout);
        }
    }
}

final class StreamIpcPeerTest extends TestCase
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
