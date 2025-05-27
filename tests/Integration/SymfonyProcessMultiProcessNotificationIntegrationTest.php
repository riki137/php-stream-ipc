<?php
declare(strict_types=1);

namespace PhpStreamIpc\Tests\Integration;

use PHPUnit\Framework\TestCase;
use PhpStreamIpc\SymfonyIpcPeer;
use PhpStreamIpc\Message\LogMessage;
use PhpStreamIpc\Message\Message;
use Symfony\Component\Process\Process;

final class SymfonyProcessMultiProcessNotificationIntegrationTest extends TestCase
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
usleep(100_000);
$session->notify(new LogMessage('p1_2', 'info'));
PHP;
        file_put_contents(
            $this->scriptPath1,
            sprintf($script1, var_export($autoloadPath, true))
        );

        $this->scriptPath2 = sys_get_temp_dir() . '/ipc_multi_notify_p2_' . uniqid() . '.php';
        $script2 = <<<'PHP'
<?php
declare(strict_types=1);

require %s;

use PhpStreamIpc\StreamIpcPeer;
use PhpStreamIpc\Message\LogMessage;

$peer    = new StreamIpcPeer();
$session = $peer->createStdioSession();

usleep(50_000);
$session->notify(new LogMessage('p2_1', 'info'));
usleep(100_000);
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

    public function testMultipleSymfonyProcessesNotificationsOrder(): void
    {
        $process1 = new Process([PHP_BINARY, $this->scriptPath1]);
        $process2 = new Process([PHP_BINARY, $this->scriptPath2]);

        $peer     = new SymfonyIpcPeer();
        $session1 = $peer->createSymfonyProcessSession($process1);
        $session2 = $peer->createSymfonyProcessSession($process2);

        $received = [];
        $handler = function (Message $msg) use (&$received): void {
            $this->assertInstanceOf(LogMessage::class, $msg);
            $received[] = $msg->message;
        };

        $session1->onMessage($handler);
        $session2->onMessage($handler);

        $start = microtime(true);
        while (count($received) < 4 && (microtime(true) - $start) < 1.0) {
            $peer->tick(0.1);
        }

        $process1->stop();
        $process2->stop();

        $this->assertSame(['p1_1', 'p2_1', 'p1_2', 'p2_2'], $received);
    }
}
