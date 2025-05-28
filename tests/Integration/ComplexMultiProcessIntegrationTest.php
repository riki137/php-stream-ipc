<?php

declare(strict_types=1);

namespace StreamIpc\Tests\Integration;

use PHPUnit\Framework\TestCase;
use StreamIpc\NativeIpcPeer;
use StreamIpc\Message\LogMessage;
use StreamIpc\Message\Message;

final class ComplexMultiProcessIntegrationTest extends TestCase
{
    private array $scriptPaths = [];

    protected function setUp(): void
    {
        parent::setUp();

        $autoloadPath = realpath(__DIR__ . '/../../vendor/autoload.php');
        if (!$autoloadPath) {
            $this->markTestSkipped('Could not locate vendor/autoload.php');
        }

        // Write 5 child scripts
        for ($i = 1; $i <= 5; $i++) {
            $path = sys_get_temp_dir() . '/ipc_child_' . $i . '_' . uniqid() . '.php';
            $this->scriptPaths[$i] = $path;

            $script = <<<'PHP'
<?php
declare(strict_types=1);

require %s;

use StreamIpc\NativeIpcPeer;
use StreamIpc\Message\LogMessage;

// child #%d: two notifications, then one request
$peer    = new NativeIpcPeer();
$session = $peer->createStdioSession();

// first notification
$session->notify(new LogMessage('child%d-notify1', 'info'));

// staggered delay to encourage interleaving
usleep(100000 * %d);

// request and wait for response
$response = $session->request(new LogMessage('child%d-request1', 'info'), 1.0)->await();

// second notification
usleep(50000);
$session->notify(new LogMessage('child%d-notify2', 'info'));
PHP;

            file_put_contents(
                $path,
                sprintf(
                    $script,
                    var_export($autoloadPath, true),
                    $i, // for comment
                    $i, // notify1 message
                    $i, // delay multiplier
                    $i, // request1 message
                    $i  // notify2 message
                )
            );
        }
    }

    protected function tearDown(): void
    {
        // Clean up the temp scripts
        foreach ($this->scriptPaths as $path) {
            @unlink($path);
        }
        parent::tearDown();
    }

    public function testMasterHandlesFiveChildren(): void
    {
        $peer     = new NativeIpcPeer();
        $procs    = [];
        $sessions = [];

        // Arrays to collect what the master sees
        $notifications = [];
        $requests      = [];

        // Launch each child and wire up a session
        $descriptors = [
            0 => ['pipe', 'r'], // child STDIN
            1 => ['pipe', 'w'], // child STDOUT
            2 => ['pipe', 'w'], // child STDERR
        ];

        foreach ($this->scriptPaths as $i => $script) {
            $proc = proc_open([PHP_BINARY, $script], $descriptors, $pipes);
            $this->assertIsResource($proc, "Failed to start child process #{$i}");
            [$stdin, $stdout, $stderr] = $pipes;

            // parent session reads from both child's STDOUT and STDERR
            $session = $peer->createStreamSession($stdin, $stdout, $stderr);
            $sessions[] = $session;
            $procs[]    = $proc;

            // record any notification from child
            $session->onMessage(function(Message $msg) use (&$notifications) {
                $this->assertInstanceOf(LogMessage::class, $msg);
                $notifications[] = $msg->message;
            });

            // handle any request from child and reply immediately
            $session->onRequest(function(Message $msg, $session) use (&$requests) {
                $this->assertInstanceOf(LogMessage::class, $msg);
                $requests[] = $msg->message;
                return new LogMessage('resp-' . $msg->message, 'info');
            });
        }

        // run the event loop until we've seen all expected messages or timeout
        $start = microtime(true);
        while (
            (count($notifications) < 10 || count($requests) < 5) &&
            (microtime(true) - $start) < 5.0
        ) {
            $peer->tick(0.1);
        }

        // clean up child processes
        foreach ($procs as $proc) {
            proc_close($proc);
        }

        // Assertions:

        // Each child sent exactly 2 notifications
        $this->assertCount(10, $notifications, 'Expected 10 notifications total (2 per child)');
        // Each child made exactly 1 request
        $this->assertCount(5, $requests, 'Expected 5 requests total (1 per child)');

        // Check that for each i, we saw "child{i}-notify1", "child{i}-notify2", and "child{i}-request1"
        for ($i = 1; $i <= 5; $i++) {
            $this->assertContains("child{$i}-notify1", $notifications);
            $this->assertContains("child{$i}-notify2", $notifications);
            $this->assertContains("child{$i}-request1", $requests);
        }
    }
}
