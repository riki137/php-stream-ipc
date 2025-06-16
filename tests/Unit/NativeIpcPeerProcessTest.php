<?php

namespace StreamIpc {
    function proc_open($command, $descriptorspec, &$pipes = null, $cwd = null, $env = null, $options = null) {
        $override = $GLOBALS['proc_open_override'] ?? null;
        if ($override === 'fail_resource') {
            trigger_error('fail open', E_USER_WARNING);
            return false;
        }
        if ($override === 'wrong_pipes') {
            $realPipes = [];
            $proc = \proc_open($command, $descriptorspec, $realPipes, $cwd, $env, $options);
            $pipes = [$realPipes[0] ?? null];
            return $proc;
        }
        return \proc_open($command, $descriptorspec, $pipes, $cwd, $env, $options);
    }
}

namespace StreamIpc\Tests\Unit {

use PHPUnit\Framework\TestCase;
use StreamIpc\NativeIpcPeer;
use StreamIpc\Transport\NativeMessageTransport;
use StreamIpc\Serialization\NativeMessageSerializer;
use ReflectionProperty;
use RuntimeException;

final class NativeIpcPeerProcessTest extends TestCase
{
    public function testCreateSessionFromTransportAndRemoveSession(): void
    {
        $peer = new NativeIpcPeer();
        $write = fopen('php://memory', 'r+');
        $r1 = fopen('php://memory', 'r+');
        $r2 = fopen('php://memory', 'r+');
        $transport = new NativeMessageTransport($write, [$r1, $r2], new NativeMessageSerializer());

        $session = $peer->createSessionFromTransport($transport);
        $prop = new ReflectionProperty(NativeIpcPeer::class, 'readSet');
        $prop->setAccessible(true);
        $readSet = $prop->getValue($peer);
        $this->assertCount(2, $readSet);
        $this->assertContains($r1, $readSet);
        $this->assertContains($r2, $readSet);

        $peer->removeSession($session);
        $readSet = $prop->getValue($peer);
        $this->assertSame([], $readSet);

        $sessProp = new ReflectionProperty(\StreamIpc\IpcPeer::class, 'sessions');
        $sessProp->setAccessible(true);
        $this->assertSame([], $sessProp->getValue($peer));
    }

    public function testTickReturnsEarlyWithoutStreams(): void
    {
        $peer = new NativeIpcPeer();
        $peer->tick();
        $prop = new ReflectionProperty(NativeIpcPeer::class, 'readSet');
        $prop->setAccessible(true);
        $this->assertSame([], $prop->getValue($peer));
    }

    public function testCreateCommandSessionFailsToStartProcess(): void
    {
        $GLOBALS['proc_open_override'] = 'fail_resource';
        $peer = new NativeIpcPeer();
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to start command process');
        try {
            $peer->createCommandSession('php -r ""', []);
        } finally {
            unset($GLOBALS['proc_open_override']);
        }
    }

    public function testCreateCommandSessionFailsWithMissingPipes(): void
    {
        $GLOBALS['proc_open_override'] = 'wrong_pipes';
        $peer = new NativeIpcPeer();
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to open all required process pipes');
        try {
            $peer->createCommandSession('php -r ""', []);
        } finally {
            unset($GLOBALS['proc_open_override']);
        }
    }
}
}
