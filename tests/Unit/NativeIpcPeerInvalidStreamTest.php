<?php
namespace StreamIpc\Tests\Unit;

use PHPUnit\Framework\TestCase;
use StreamIpc\InvalidStreamException;
use StreamIpc\NativeIpcPeer;

final class NativeIpcPeerInvalidStreamTest extends TestCase
{
    public function testTickTriggersExceptionHandlerAndRemovesSession(): void
    {
        [$a, $b] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
        $peer = new NativeIpcPeer();
        $session = $peer->createStreamSession($a, $a, $b);
        fclose($b);

        $called = false;
        $session->onException(function (InvalidStreamException $e) use (&$called, $session) {
            $called = true;
            \PHPUnit\Framework\Assert::assertSame($session, $e->getSession());
        });

        $peer->tick();

        $this->assertTrue($called);

        $prop = new \ReflectionProperty($peer, 'sessions');
        $prop->setAccessible(true);
        $this->assertSame([], $prop->getValue($peer));

        fclose($a);
    }
}
