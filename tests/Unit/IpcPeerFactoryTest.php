<?php
declare(strict_types=1);

namespace Tests\PhpStreamIpc\Unit;

use DG\BypassFinals;
use PHPUnit\Framework\TestCase;
use Amp\Process\Process;
use Amp\ByteStream\WritableResourceStream;
use Amp\ByteStream\ReadableResourceStream;
use PhpStreamIpc\IpcPeer;
use PhpStreamIpc\IpcSession;

final class IpcPeerFactoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        BypassFinals::enable();
    }

    public function testCreateStreamSessionProducesIpcSession(): void
    {
        $writeRes = fopen('php://temp', 'r+');
        $readRes  = fopen('php://temp', 'r+');
        $write    = new WritableResourceStream($writeRes);
        $read     = new ReadableResourceStream($readRes);

        $peer    = new IpcPeer();
        $session = $peer->createStreamSession($write, $read);

        $this->assertInstanceOf(IpcSession::class, $session);
    }

    public function testCreateProcessSessionProducesIpcSession(): void
    {
        // stub Process with fake streams
        $writeRes = fopen('php://temp', 'r+');
        $readRes  = fopen('php://temp', 'r+');
        $write    = new WritableResourceStream($writeRes);
        $readOut  = new ReadableResourceStream($readRes);
        $readErr  = new ReadableResourceStream($readRes);

        $proc = $this->getMockBuilder(Process::class)
            ->disableOriginalConstructor()
            ->getMock();
        $proc->method('getStdin')->willReturn($write);
        $proc->method('getStdout')->willReturn($readOut);
        $proc->method('getStderr')->willReturn($readErr);

        $peer    = new IpcPeer();
        $session = $peer->createProcessSession($proc);

        $this->assertInstanceOf(IpcSession::class, $session);
    }
}
