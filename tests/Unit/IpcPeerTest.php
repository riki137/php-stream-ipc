<?php
namespace PhpStreamIpc\Tests\Unit;

use PhpStreamIpc\IpcPeer;
use PhpStreamIpc\Tests\Fixtures\FakeTransport;
use PhpStreamIpc\Tests\Fixtures\FakeTransportB;
use PHPUnit\Framework\TestCase;

final class IpcPeerTest extends TestCase
{
    public function testTickCallsOncePerTransportClass(): void
    {
        $peer = new IpcPeer();
        $t1 = new FakeTransport();
        $t2 = new FakeTransport();
        $tb = new FakeTransportB();
        $s1 = $peer->createSession($t1);
        $s2 = $peer->createSession($t2);
        $s3 = $peer->createSession($tb);

        $peer->tick(0.5);

        $this->assertSame([0.5], $t1->ticks);
        $this->assertSame([], $t2->ticks);
        $this->assertSame([0.5], $tb->ticks);
        $this->assertSame([$s1, $s2], $t1->sessionArgs[0]);
        $this->assertSame([$s3], $tb->sessionArgs[0]);
    }

    public function testTickForLoopsUntilTimeout(): void
    {
        $peer = new IpcPeer();
        $t = new FakeTransport();
        $peer->createSession($t);

        $peer->tickFor(0.02);
        $this->assertNotEmpty($t->ticks);
        $count = count($t->ticks);

        $peer->tickFor(0.0);
        $this->assertCount($count, $t->ticks);
    }

    public function testClosedSessionNoLongerTicked(): void
    {
        $peer = new IpcPeer();
        $t = new FakeTransport();
        $s = $peer->createSession($t);

        $s->close();
        $peer->tick();

        $this->assertSame([], $t->ticks);
    }
}
