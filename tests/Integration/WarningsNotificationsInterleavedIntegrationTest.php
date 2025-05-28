<?php
declare(strict_types=1);

namespace StreamIpc\Tests\Integration;

use PHPUnit\Framework\TestCase;
use StreamIpc\NativeIpcPeer;
use StreamIpc\Message\LogMessage;
use StreamIpc\Message\Message;

final class WarningsNotificationsInterleavedIntegrationTest extends TestCase
{
    private string $scriptPath;

    protected function setUp(): void
    {
        parent::setUp();

        $autoloadPath = realpath(__DIR__ . '/../../vendor/autoload.php');
        if (!$autoloadPath) {
            $this->markTestSkipped('Could not locate vendor/autoload.php');
        }

        // ── Child script ──────────────────────────────────────────────────────────
        $this->scriptPath = sys_get_temp_dir() . '/ipc_warn_notify_' . uniqid() . '.php';
        $script = <<<'PHP'
<?php
declare(strict_types=1);

require %s;

use StreamIpc\NativeIpcPeer;
use StreamIpc\Message\LogMessage;

ini_set('display_errors', 'stderr');
error_reporting(E_ALL);

$peer    = new NativeIpcPeer();
$session = $peer->createStdioSession();

trigger_error('warn1', E_USER_WARNING);
$session->notify(new LogMessage('notify1', 'info'));

trigger_error('warn2', E_USER_WARNING);
$session->notify(new LogMessage('notify2', 'info'));

trigger_error('warn3', E_USER_WARNING);
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

    public function testWarningsDoNotDisruptNotifications(): void
    {
        $descriptors = [
            0 => ['pipe', 'r'], // child STDIN
            1 => ['pipe', 'w'], // child STDOUT  (framed IPC)
            2 => ['pipe', 'w'], // child STDERR  (plain warnings)
        ];

        $proc = proc_open([PHP_BINARY, $this->scriptPath], $descriptors, $pipes);
        $this->assertIsResource($proc, 'Failed to start child process');

        [$stdin, $stdout, $stderr] = $pipes;

        $peer    = new NativeIpcPeer();
        // listen to both STDOUT and STDERR so we see framed msgs *and* raw warnings
        $session = $peer->createStreamSession($stdin, $stdout, $stderr);

        $notifications = [];
        $warningsBuf   = '';

        $session->onMessage(function (Message $msg) use (&$notifications, &$warningsBuf): void {
            $this->assertInstanceOf(LogMessage::class, $msg);
            if ($msg->level === 'info') {
                $notifications[] = $msg->message;       // framed notifications
            } else {               // junk from STDERR
                $warningsBuf .= $msg->message;          // junk from STDERR
            }
        });

        // Tick until we’ve seen all expected output or we time-out
        $start = microtime(true);
        while (
            (count($notifications) < 2 || !str_contains($warningsBuf, 'warn3')) &&
            (microtime(true) - $start) < 2.0
        ) {
            $peer->tick(0.1);
        }

        proc_close($proc);

        // ── Assertions ───────────────────────────────────────────────────────────
        $this->assertSame(['notify1', 'notify2'], $notifications, 'Missing notifications');

        foreach (['warn1', 'warn2', 'warn3'] as $warn) {
            $this->assertTrue(
                str_contains($warningsBuf, "Warning: {$warn}") ||
                str_contains($warningsBuf, "PHP Warning:  {$warn}"),
                "Missing warning {$warn}"
            );
        }
    }
}
