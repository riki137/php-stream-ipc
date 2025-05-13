#!/usr/bin/env php
<?php

declare(strict_types=1);
putenv('AMP_DEBUG=1');
require __DIR__ . '/../../vendor/autoload.php';

use Amp\Process\Process;
use PhpStreamIpc\IpcPeer;
use PhpStreamIpc\Message\LogMessage;
use Revolt\EventLoop;

// 1. Launch the slave.php fixture as a child Amp process.
$slave = Process::start(['php', __DIR__ . '/../Fixtures/slave.php']);
// start() returns an Amp\Future—await it to actually spawn.
echo "👶 Slave started (PID {$slave->getPid()})\n\n";

// 2. Wire up IPC over the child's stdio
$peer = new IpcPeer();
$session = $peer->createProcessSession($slave);

// 3. REQUEST → RESPONSE
echo "🚀 Master → sending request: \"hello from master\"\n";
try {
    /** @var LogMessage $response */
    $response = $session
        ->request(new LogMessage('hello from master', 'info'))
        ->await();
} catch (\Throwable $e) {
    echo $e->getMessage();
    exit(1);
}
echo "✅ Got response: \"{$response->message}\" (level={$response->level})\n\n";

// 4. NOTIFY (fire-and-forget)
echo "🔔 Master → sending notification: \"this is a broadcast\"\n";
$session->notify(new LogMessage('this is a broadcast', 'notice'));

// give the slave a moment to process the notification (no response expected)
\usleep(100_000);

// 5. Clean up
echo "\n🛑 Closing session and waiting for slave to exit…\n";
$session->close();

$slave->kill();
// join() returns a Future<ProcessState>
echo "🔚 Slave exited with code {$slave->join()}\n";
