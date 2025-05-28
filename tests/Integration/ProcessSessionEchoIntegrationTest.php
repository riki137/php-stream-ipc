<?php
declare(strict_types=1);

namespace StreamIpc\Tests\Integration;

use PHPUnit\Framework\TestCase;
use StreamIpc\NativeIpcPeer;
use StreamIpc\Message\LogMessage;
use StreamIpc\Message\Message;

final class ProcessSessionEchoIntegrationTest extends TestCase
{
    private string $scriptPath;

    protected function setUp(): void
    {
        parent::setUp();

        $autoloadPath = realpath(__DIR__ . '/../../vendor/autoload.php');
        if (!$autoloadPath) {
            $this->markTestSkipped('Could not locate vendor/autoload.php');
        }

        // Write a tiny echo‐server that uses STDIO
        $this->scriptPath = sys_get_temp_dir() . '/ipc_echo_' . uniqid() . '.php';
        $script = <<<'PHP'
<?php
declare(strict_types=1);

require %s;

use StreamIpc\NativeIpcPeer;
use StreamIpc\Message\Message;

$peer    = new NativeIpcPeer();
$session = $peer->createStdioSession();

// on any request, just send the same Message back
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

    public function testEchoesLogMessage(): void
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

        $msg      = new LogMessage('hello world', 'info');
        $response = $session->request($msg, 1.0)->await();

        $this->assertInstanceOf(LogMessage::class, $response);
        $this->assertSame('hello world', $response->message);
        $this->assertSame('info', $response->level);

        proc_close($process);
    }
}
