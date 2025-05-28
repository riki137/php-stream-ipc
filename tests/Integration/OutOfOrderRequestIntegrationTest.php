<?php

declare(strict_types=1);

namespace StreamIpc\Tests\Integration;

use PHPUnit\Framework\TestCase;
use StreamIpc\NativeIpcPeer;
use StreamIpc\Message\LogMessage;
use StreamIpc\Message\Message;

final class OutOfOrderRequestIntegrationTest extends TestCase
{
    private string $scriptPath;

    protected function setUp(): void
    {
        parent::setUp();

        $autoload = realpath(__DIR__ . '/../../vendor/autoload.php');
        if (!$autoload) {
            $this->markTestSkipped('Could not locate vendor/autoload.php');
        }

        // A simple echo-server that never exits, so it can handle multiple requests.
        $this->scriptPath = sys_get_temp_dir() . '/ipc_echo_loop_' . uniqid() . '.php';
        $script = <<<'PHP'
<?php
declare(strict_types=1);

require %s;

use StreamIpc\NativeIpcPeer;
use StreamIpc\Message\Message;

// Create a peer that echoes any request back immediately
$peer    = new NativeIpcPeer();
$session = $peer->createStdioSession();
$session->onRequest(function(Message $msg, $session): Message {
    return $msg;
});

// Keep the event loop alive to serve all incoming requests
while (true) {
    $peer->tick();
}
PHP;
        file_put_contents(
            $this->scriptPath,
            sprintf($script, var_export($autoload, true))
        );
    }

    protected function tearDown(): void
    {
        @unlink($this->scriptPath);
        parent::tearDown();
    }

    public function testOutOfOrderRequestResponse(): void
    {
        $descriptors = [
            0 => ['pipe', 'r'], // child STDIN
            1 => ['pipe', 'w'], // child STDOUT
            2 => ['pipe', 'w'], // child STDERR
        ];

        // Launch two identical echo-servers
        $proc1 = proc_open([PHP_BINARY, $this->scriptPath], $descriptors, $pipes1);
        $this->assertIsResource($proc1, 'Failed to start process 1');
        [$stdin1, $stdout1, $stderr1] = $pipes1;

        $proc2 = proc_open([PHP_BINARY, $this->scriptPath], $descriptors, $pipes2);
        $this->assertIsResource($proc2, 'Failed to start process 2');
        [$stdin2, $stdout2, $stderr2] = $pipes2;

        $peer     = new NativeIpcPeer();
        $session1 = $peer->createStreamSession($stdin1, $stdout1, $stderr1);
        $session2 = $peer->createStreamSession($stdin2, $stdout2, $stderr2);

        // Send two requests to each process
        $p1m1 = $session1->request(new LogMessage('p1_m1', 'info'), 1.0);
        $p2m1 = $session2->request(new LogMessage('p2_m1', 'info'), 1.0);
        $p1m2 = $session1->request(new LogMessage('p1_m2', 'info'), 1.0);
        $p2m2 = $session2->request(new LogMessage('p2_m2', 'info'), 1.0);

        // Await them in a completely different order:
        $respP2M2 = $p2m2->await();
        $this->assertSame('p2_m2', $respP2M2->message);
        $this->assertSame('info',   $respP2M2->level);

        $respP1M1 = $p1m1->await();
        $this->assertSame('p1_m1', $respP1M1->message);

        $respP2M1 = $p2m1->await();
        $this->assertSame('p2_m1', $respP2M1->message);

        $respP1M2 = $p1m2->await();
        $this->assertSame('p1_m2', $respP1M2->message);

        // Clean up child processes
        proc_terminate($proc1);
        proc_terminate($proc2);
        proc_close($proc1);
        proc_close($proc2);
    }
}
