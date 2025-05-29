<?php

declare(strict_types=1);

namespace StreamIpc\Tests\Integration;

use PHPUnit\Framework\TestCase;
use StreamIpc\NativeIpcPeer;
use StreamIpc\Message\LogMessage;
use StreamIpc\Message\Message;

final class ImmediateReadIntegrationTest extends TestCase
{
    private string $scriptPath;

    protected function setUp(): void
    {
        parent::setUp();

        $autoloadPath = realpath(__DIR__ . '/../../vendor/autoload.php');
        if (!$autoloadPath) {
            $this->markTestSkipped('Could not locate vendor/autoload.php');
        }

        $this->scriptPath = sys_get_temp_dir() . '/ipc_immediate_' . uniqid() . '.php';
        $script = <<<'PHP'
<?php
declare(strict_types=1);

require %s;

use StreamIpc\NativeIpcPeer;
use StreamIpc\Message\LogMessage;

$peer    = new NativeIpcPeer();
$session = $peer->createStdioSession();

$session->notify(new LogMessage('ready', 'info'));

sleep(60);
PHP;
        file_put_contents(
            $this->scriptPath,
            sprintf($script, var_export($autoloadPath, true))
        );
    }

    protected function tearDown(): void
    {
        @unlink($this->scriptPath);
        parent::tearDown();
    }

    public function testMessageIsReadImmediately(): void
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = proc_open([PHP_BINARY, $this->scriptPath], $descriptors, $pipes);
        $this->assertIsResource($process, 'Failed to start child process');

        [$stdin, $stdout, $stderr] = $pipes;

        $peer    = new NativeIpcPeer();
        $session = $peer->createStreamSession($stdin, $stdout, $stderr);

        $received = false;
        $session->onMessage(function (Message $msg) use (&$received): void {
            $this->assertInstanceOf(LogMessage::class, $msg);
            $received = true;
        });

        $start = microtime(true);
        $peer->tickFor(1.0);
        $this->assertTrue($received, 'Message should be received before stream closes');

        proc_terminate($process);
        proc_close($process);
    }
}
