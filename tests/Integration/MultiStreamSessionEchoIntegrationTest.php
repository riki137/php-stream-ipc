<?php

declare(strict_types=1);

namespace PhpStreamIpc\Tests\Integration;

use PHPUnit\Framework\TestCase;
use PhpStreamIpc\StreamIpcPeer;
use PhpStreamIpc\Message\LogMessage;

final class MultiStreamSessionEchoIntegrationTest extends TestCase
{
    private string $scriptPath;

    protected function setUp(): void
    {
        parent::setUp();

        $autoloadPath = realpath(__DIR__ . '/../../vendor/autoload.php');
        if (!$autoloadPath) {
            $this->markTestSkipped('Could not locate vendor/autoload.php');
        }

        // Write a server that echoes requests back on STDERR instead of STDOUT
        $this->scriptPath = sys_get_temp_dir() . '/ipc_multi_echo_' . uniqid() . '.php';
        $script = <<<'PHP'
<?php
declare(strict_types=1);

require %s;

use PhpStreamIpc\StreamIpcPeer;
use PhpStreamIpc\Message\Message;

$peer    = new StreamIpcPeer();
// write to STDERR (fd 2), read from STDIN (fd 0)
$session = $peer->createStreamSession(STDERR, STDIN);

$session->onRequest(function(Message $msg, $session): Message {
    return $msg;
});

$peer->tick();
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

    public function testEchoesOnEitherStream(): void
    {
        $descriptors = [
            0 => ['pipe', 'r'], // child STDIN
            1 => ['pipe', 'w'], // child STDOUT (unused by server)
            2 => ['pipe', 'w'], // child STDERR (response)
        ];
        $process = proc_open([PHP_BINARY, $this->scriptPath], $descriptors, $pipes);
        $this->assertIsResource($process, 'Failed to start child process');

        [$stdin, $stdout, $stderr] = $pipes;

        $peer    = new StreamIpcPeer();
        // parent reads from both $stdout and $stderr
        $session = $peer->createStreamSession($stdin, $stdout, $stderr);

        $msg      = new LogMessage('multi-stream hello', 'info');
        $response = $session->request($msg, 1.0)->await();

        $this->assertInstanceOf(LogMessage::class, $response);
        $this->assertSame('multi-stream hello', $response->message);
        $this->assertSame('info', $response->level);

        proc_close($process);
    }
}
