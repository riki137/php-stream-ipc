<?php
declare(strict_types=1);

namespace PhpStreamIpc\Tests\Integration;

use PHPUnit\Framework\TestCase;
use PhpStreamIpc\SymfonyIpcPeer;
use PhpStreamIpc\Message\LogMessage;
use PhpStreamIpc\Message\Message;
use Symfony\Component\Process\Process;

final class SymfonyProcessSessionEchoIntegrationTest extends TestCase
{
    private string $scriptPath;

    protected function setUp(): void
    {
        parent::setUp();

        $autoloadPath = realpath(__DIR__ . '/../../vendor/autoload.php');
        if (!$autoloadPath) {
            $this->markTestSkipped('Could not locate vendor/autoload.php');
        }

        $this->scriptPath = sys_get_temp_dir() . '/ipc_echo_' . uniqid() . '.php';
        $script = <<<'PHP'
<?php
require %s;

use PhpStreamIpc\StreamIpcPeer;
use PhpStreamIpc\Message\Message;

$peer = new StreamIpcPeer();
$session = $peer->createStdioSession();
$session->onRequest(fn(Message $m) => $m);
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

    public function testEchoWithSymfonyProcess(): void
    {
        $process = new Process([PHP_BINARY, $this->scriptPath]);
        $peer = new SymfonyIpcPeer();
        $session = $peer->createSymfonyProcessSession($process);

        $msg = new LogMessage('hi', 'info');
        $resp = $session->request($msg, 1.0)->await();

        $this->assertInstanceOf(LogMessage::class, $resp);
        $this->assertSame('hi', $resp->message);
        $this->assertSame('info', $resp->level);

        $process->stop();
    }
}
