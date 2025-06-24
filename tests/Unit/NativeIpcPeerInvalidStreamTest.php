<?php
namespace StreamIpc\Tests\Unit;

use PHPUnit\Framework\TestCase;
use StreamIpc\InvalidStreamException;
use StreamIpc\NativeIpcPeer;

final class NativeIpcPeerInvalidStreamTest extends TestCase
{
    public function testTickThrowsInvalidStreamExceptionWithSession(): void
    {
        [$a, $b] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
        $peer = new NativeIpcPeer();
        $session = $peer->createStreamSession($a, $a, $b);
        fclose($b);

        try {
            $peer->tick();
            $this->fail('No exception thrown');
        } catch (InvalidStreamException $e) {
            $this->assertSame($session, $e->getSession());
        } finally {
            fclose($a);
        }
    }
}
