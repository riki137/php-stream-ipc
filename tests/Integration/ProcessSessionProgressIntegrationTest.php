<?php

declare(strict_types=1);

namespace PhpStreamIpc\Tests\Integration;

use PHPUnit\Framework\TestCase;
use PhpStreamIpc\IpcPeer;
use PhpStreamIpc\Message\LogMessage;
use PhpStreamIpc\Message\Message;

final class ProcessSessionProgressIntegrationTest extends TestCase
{
    private string $scriptPath;

    protected function setUp(): void
    {
        parent::setUp();

        $autoloadPath = realpath(__DIR__ . '/../../vendor/autoload.php');
        if (!$autoloadPath) {
            $this->markTestSkipped('Could not locate vendor/autoload.php');
        }

        // Write a tiny progress‐reporting server that uses STDIO
        $this->scriptPath = sys_get_temp_dir() . '/ipc_progress_' . uniqid() . '.php';
        $script = <<<'PHP'
<?php
declare(strict_types=1);

require %s;

use PhpStreamIpc\IpcPeer;
use PhpStreamIpc\Message\LogMessage;
use PhpStreamIpc\Message\Message;

$peer    = new IpcPeer();
$session = $peer->createStdioSession();

$session->onRequest(function(Message $msg, $session): Message {
    // send interim progress notifications
    $session->notify(new LogMessage('step1', 'info'));
    usleep(100_000); // simulate work
    $session->notify(new LogMessage('step2', 'info'));
    usleep(100_000);
    // final response
    return new LogMessage('done', 'info');
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

    public function testReceivesProgressNotificationsAndFinalResponse(): void
    {
        $descriptors = [
            0 => ['pipe', 'r'], // child STDIN
            1 => ['pipe', 'w'], // child STDOUT
            2 => ['pipe', 'w'], // child STDERR
        ];
        $process = proc_open([PHP_BINARY, $this->scriptPath], $descriptors, $pipes);
        $this->assertIsResource($process, 'Failed to start child process');

        [$stdin, $stdout, $stderr] = $pipes;

        $peer    = new IpcPeer();
        $session = $peer->createStreamSession($stdin, $stdout, $stderr);

        $progress = [];
        $session->onMessage(function(Message $msg) use (&$progress) {
            $this->assertInstanceOf(LogMessage::class, $msg);
            $progress[] = $msg->message;
        });

        $response = $session->request(new LogMessage('start', 'info'), 2.0)->await();

        // verify interim steps
        $this->assertSame(['step1', 'step2'], $progress);
        // verify final response
        $this->assertInstanceOf(LogMessage::class, $response);
        $this->assertSame('done', $response->message);
        $this->assertSame('info', $response->level);

        proc_close($process);
    }
}
