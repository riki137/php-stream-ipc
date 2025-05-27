<?php

declare(strict_types=1);

namespace PhpStreamIpc\Tests\Integration;

use PHPUnit\Framework\TestCase;
use PhpStreamIpc\StreamIpcPeer;
use PhpStreamIpc\Message\LogMessage;
use PhpStreamIpc\Message\Message;

final class MultiProcessNotificationIntegrationTest extends TestCase
{
    private string $scriptPath1;
    private string $scriptPath2;

    protected function setUp(): void
    {
        parent::setUp();

        $autoloadPath = realpath(__DIR__ . '/../../vendor/autoload.php');
        if (!$autoloadPath) {
            $this->markTestSkipped('Could not locate vendor/autoload.php');
        }

        // First subprocess: sends p1_1 immediately, then p1_2 after 100ms
        $this->scriptPath1 = sys_get_temp_dir() . '/ipc_multi_notify_p1_' . uniqid() . '.php';
        $script1 = <<<'PHP'
<?php
declare(strict_types=1);

require %s;

use PhpStreamIpc\StreamIpcPeer;
use PhpStreamIpc\Message\LogMessage;

$peer    = new StreamIpcPeer();
$session = $peer->createStdioSession();

$session->notify(new LogMessage('p1_1', 'info'));
usleep(100_000); // 100 ms
$session->notify(new LogMessage('p1_2', 'info'));
PHP;
        file_put_contents(
            $this->scriptPath1,
            sprintf($script1, var_export($autoloadPath, true))
        );

        // Second subprocess: waits 50ms, sends p2_1, then p2_2 after another 100ms
        $this->scriptPath2 = sys_get_temp_dir() . '/ipc_multi_notify_p2_' . uniqid() . '.php';
        $script2 = <<<'PHP'
<?php
declare(strict_types=1);

require %s;

use PhpStreamIpc\StreamIpcPeer;
use PhpStreamIpc\Message\LogMessage;

$peer    = new StreamIpcPeer();
$session = $peer->createStdioSession();

usleep(50_000); // 50 ms
$session->notify(new LogMessage('p2_1', 'info'));
usleep(100_000); // 100 ms
$session->notify(new LogMessage('p2_2', 'info'));
PHP;
        file_put_contents(
            $this->scriptPath2,
            sprintf($script2, var_export($autoloadPath, true))
        );
    }

    protected function tearDown(): void
    {
        @unlink($this->scriptPath1);
        @unlink($this->scriptPath2);
        parent::tearDown();
    }

    public function testMultipleProcessesNotificationsOrder(): void
    {
        // Launch both child processes
        $descriptors = [
            0 => ['pipe', 'r'], // child STDIN
            1 => ['pipe', 'w'], // child STDOUT
            2 => ['pipe', 'w'], // child STDERR (unused)
        ];

        $proc1 = proc_open([PHP_BINARY, $this->scriptPath1], $descriptors, $pipes1);
        $this->assertIsResource($proc1, 'Failed to start process 1');
        [$stdin1, $stdout1, $stderr1] = $pipes1;

        $proc2 = proc_open([PHP_BINARY, $this->scriptPath2], $descriptors, $pipes2);
        $this->assertIsResource($proc2, 'Failed to start process 2');
        [$stdin2, $stdout2, $stderr2] = $pipes2;

        // Parent peer with two sessions
        $peer = new StreamIpcPeer();
        $session1 = $peer->createStreamSession($stdin1, $stdout1, $stderr1);
        $session2 = $peer->createStreamSession($stdin2, $stdout2, $stderr2);

        $received = [];
        $handler = function (Message $msg) use (&$received) {
            $received[] = $msg->message;
        };

        $session1->onMessage($handler);
        $session2->onMessage($handler);

        // Tick until all four notifications arrive or timeout after 1 s
        $start = microtime(true);
        while (count($received) < 4 && (microtime(true) - $start) < 1.0) {
            $peer->tick(0.2);
        }

        // Clean up child processes
        proc_close($proc1);
        proc_close($proc2);

        $this->assertSame(
            ['p1_1', 'p2_1', 'p1_2', 'p2_2'],
            $received,
            'Notifications should arrive in the interleaved order p1_1, p2_1, p1_2, p2_2'
        );
    }
}
