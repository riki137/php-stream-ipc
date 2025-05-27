<?php
declare(strict_types=1);

namespace PhpStreamIpc\Tests\Integration;

use PHPUnit\Framework\TestCase;
use PhpStreamIpc\SymfonyIpcPeer;
use PhpStreamIpc\Message\LogMessage;
use PhpStreamIpc\Message\Message;
use Symfony\Component\Process\Process;

final class SymfonyProcessDifferentStreamsIntegrationTest extends TestCase
{
    private string $stdoutScript;
    private string $stderrScript;

    protected function setUp(): void
    {
        parent::setUp();

        $autoloadPath = realpath(__DIR__ . '/../../vendor/autoload.php');
        if (!$autoloadPath) {
            $this->markTestSkipped('Could not locate vendor/autoload.php');
        }

        $this->stdoutScript = sys_get_temp_dir() . '/ipc_stdout_echo_' . uniqid() . '.php';
        $stdout = <<<'PHP'
<?php
declare(strict_types=1);

require %s;

use PhpStreamIpc\StreamIpcPeer;
use PhpStreamIpc\Message\Message;

$peer    = new StreamIpcPeer();
$session = $peer->createStdioSession();
$session->onRequest(fn(Message $msg) => $msg);
$peer->tick();
PHP;
        file_put_contents(
            $this->stdoutScript,
            sprintf($stdout, var_export($autoloadPath, true))
        );

        $this->stderrScript = sys_get_temp_dir() . '/ipc_stderr_echo_' . uniqid() . '.php';
        $stderr = <<<'PHP'
<?php
declare(strict_types=1);

require %s;

use PhpStreamIpc\StreamIpcPeer;
use PhpStreamIpc\Message\Message;

$peer    = new StreamIpcPeer();
$session = $peer->createStreamSession(STDERR, STDIN);
$session->onRequest(fn(Message $msg) => $msg);
$peer->tick();
PHP;
        file_put_contents(
            $this->stderrScript,
            sprintf($stderr, var_export($autoloadPath, true))
        );
    }

    protected function tearDown(): void
    {
        @unlink($this->stdoutScript);
        @unlink($this->stderrScript);
        parent::tearDown();
    }

    public function testResponsesFromDifferentStreams(): void
    {
        $process1 = new Process([PHP_BINARY, $this->stdoutScript]);
        $process2 = new Process([PHP_BINARY, $this->stderrScript]);

        $peer     = new SymfonyIpcPeer();
        $session1 = $peer->createSymfonyProcessSession($process1);
        $session2 = $peer->createSymfonyProcessSession($process2);

        $pending1 = $session1->request(new LogMessage('from-stdout', 'info'), 2.0);
        $pending2 = $session2->request(new LogMessage('from-stderr', 'info'), 2.0);

        $peer->tickFor(2.0);

        $resp1 = $pending1->await();
        $resp2 = $pending2->await();

        $this->assertInstanceOf(LogMessage::class, $resp1);
        $this->assertSame('from-stdout', $resp1->message);
        $this->assertInstanceOf(LogMessage::class, $resp2);
        $this->assertSame('from-stderr', $resp2->message);

        $process1->stop();
        $process2->stop();
    }
}
