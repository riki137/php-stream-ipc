<?php

declare(strict_types=1);

namespace StreamIpc\Tests\Integration;

use PHPUnit\Framework\TestCase;
use StreamIpc\NativeIpcPeer;
use StreamIpc\Message\LogMessage;
use StreamIpc\Message\Message;

final class ProcessSessionNotificationIntegrationTest extends TestCase
{
    private string $scriptPath;

    protected function setUp(): void
    {
        parent::setUp();

        $autoloadPath = realpath(__DIR__ . '/../../vendor/autoload.php');
        if (!$autoloadPath) {
            $this->markTestSkipped('Could not locate vendor/autoload.php');
        }

        // Write a tiny notification-server that uses STDIO
        $this->scriptPath = sys_get_temp_dir() . '/ipc_notify_' . uniqid() . '.php';
        $script = <<<'PHP'
<?php
declare(strict_types=1);

require %s;

use StreamIpc\NativeIpcPeer;
use StreamIpc\Message\LogMessage;

$peer    = new NativeIpcPeer();
$session = $peer->createStdioSession();

// send two notifications and exit
$session->notify(new LogMessage('first', 'info'));
$session->notify(new LogMessage('second', 'info'));
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

    public function testReceivesNotifications(): void
    {
        $descriptors = [
            0 => ['pipe', 'r'], // child STDIN
            1 => ['pipe', 'w'], // child STDOUT
            2 => ['pipe', 'w'], // child STDERR
        ];
        $process = proc_open([PHP_BINARY, $this->scriptPath], $descriptors, $pipes);
        $this->assertIsResource($process, 'Failed to start child process');

        [$stdin, $stdout, $stderr] = $pipes;

        $peer    = new NativeIpcPeer();
        $session = $peer->createStreamSession($stdin, $stdout, $stderr);

        $received = [];
        $session->onMessage(function(Message $msg) use (&$received) {
            $this->assertInstanceOf(LogMessage::class, $msg);
            $received[] = $msg->message;
        });

        // keep ticking until we have both notifications or timeout after 1s
        $start = microtime(true);
        while (count($received) < 2 && (microtime(true) - $start) < 1.0) {
            try {
                $peer->tick(0.1);
            } catch (\RuntimeException $e) {
                // ignore "Stream closed unexpectedly", keep looping to drain any buffered frames
                if (str_contains($e->getMessage(), 'Stream closed unexpectedly')) {
                    continue;
                }
                throw $e;
            }
        }

        $this->assertSame(['first', 'second'], $received);

        proc_close($process);
    }
}
