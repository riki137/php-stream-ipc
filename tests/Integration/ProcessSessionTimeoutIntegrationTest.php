<?php

declare(strict_types=1);

namespace StreamIpc\Tests\Integration;

use PHPUnit\Framework\TestCase;
use StreamIpc\NativeIpcPeer;
use StreamIpc\Message\LogMessage;
use StreamIpc\Message\Message;

final class ProcessSessionTimeoutIntegrationTest extends TestCase
{
    private string $scriptPath;

    protected function setUp(): void
    {
        parent::setUp();

        $autoloadPath = realpath(__DIR__ . '/../../vendor/autoload.php');
        if (!$autoloadPath) {
            $this->markTestSkipped('Could not locate vendor/autoload.php');
        }

        // Write a server that never responds to requests
        $this->scriptPath = sys_get_temp_dir() . '/ipc_timeout_' . uniqid() . '.php';
        $script = <<<'PHP'
<?php
declare(strict_types=1);

require %s;

use StreamIpc\NativeIpcPeer;
use StreamIpc\Message\Message;

$peer    = new NativeIpcPeer();
$session = $peer->createStdioSession();

// register a handler that never returns a response
$session->onRequest(function(Message $msg, $session): ?Message {
    return null;
});

// keep ticking so the process stays alive
while (true) {
    $peer->tick();
}
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

    public function testRequestTimeout(): void
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

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('IPC request timed out after 0.1s');

        // use a short timeout to trigger failure
        $session->request(new LogMessage('ping', 'info'), 0.1)->await();

        // clean up
        proc_terminate($process);
        proc_close($process);
    }
}
