<?php
// tests/Fixtures/slave.php
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use PhpStreamIpc\IpcPeer;
use PhpStreamIpc\Message\LogMessage;
use Amp\ByteStream\ClosedException;

// Set up error handling
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Instantiate IPC peer over stdio
$peer = new IpcPeer();
$session = $peer->createStdioSession();

// Handle incoming LogMessage requests by uppercasing the message
$session->onRequest(function (LogMessage $msg) {
    $result = new LogMessage(strtoupper($msg->message), $msg->level);
    return $result;
});

// Loop until stdin closes, then exit
while (true) {
    try {
        $session->tick();
    } catch (ClosedException $e) {
        exit(0);
    } catch (\Throwable $e) {
        exit(1);
    }
}
