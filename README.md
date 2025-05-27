# PHP Stream IPC

[![Latest Version on Packagist](https://img.shields.io/packagist/v/riki137/php-stream-ipc.svg)](https://packagist.org/packages/riki137/php-stream-ipc)
[![Tests](https://github.com/riki137/php-stream-ipc/actions/workflows/tests.yml/badge.svg)](https://github.com/riki137/php-stream-ipc/actions/workflows/tests.yml)
[![codecov](https://codecov.io/gh/riki137/php-stream-ipc/branch/main/graph/badge.svg)](https://codecov.io/gh/riki137/php-stream-ipc)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![PHP 8.2+](https://img.shields.io/badge/php-^8.2-8892BF.svg)](https://www.php.net/)


A lightweight PHP library for Inter-Process Communication (IPC) over streams, pipes, and stdio with built-in request-response correlation, message framing, and serialization.

## 🚀 Features

- **Simple API**: Intuitive interface for sending messages between processes
- **Request-Response Correlation**: Automatic matching of responses to requests
- **Message Framing**: Reliable message boundary detection with magic number headers
- **Multiple Transport Modes**: Works with stdio, pipes, sockets, and file streams
- **Serialization Options**: Supports multiple serialization formats (native PHP, JSON)
- **Event-Driven Architecture**: Register handlers for notifications and requests
- **Timeouts**: Built-in timeout handling for requests
- **Error Handling**: Graceful handling of process crashes and stream closures

## 📦 Installation

```bash
composer require riki137/php-stream-ipc
```

## 🔍 Quick Start Guide

### Basic Parent-Child Communication

Create a parent process that communicates with a child process:

```php
// parent.php
use PhpStreamIpc\IpcPeer;
use PhpStreamIpc\Message\LogMessage;

// Launch child process
$descriptors = [
    0 => ['pipe', 'r'], // child's STDIN
    1 => ['pipe', 'w'], // child's STDOUT
    2 => ['pipe', 'w']  // child's STDERR
];
$process = proc_open('php child.php', $descriptors, $pipes);
[$stdin, $stdout, $stderr] = $pipes;

// Create IPC session
$peer = new IpcPeer();
$session = $peer->createStreamSession($stdin, $stdout, $stderr);

// Send a request to the child and wait for response
$response = $session->request(new LogMessage('Hello from parent!'), 5.0)->await();
echo "Child responded: {$response->message}\n";

// Clean up
proc_close($process);
```

Using Symfony's Process component instead of `proc_open()`:

```php
use Symfony\Component\Process\Process;

$process = new Process([PHP_BINARY, 'child.php']);
$peer = new IpcPeer();
$session = $peer->createSymfonyProcessSession($process);

$response = $session->request(new LogMessage('Hello from parent!'), 5.0)->await();
echo "Child responded: {$response->message}\n";
```

This approach requires the `symfony/process` package:

```bash
composer require symfony/process
```

```php
// child.php
use PhpStreamIpc\IpcPeer;
use PhpStreamIpc\Message\LogMessage;
use PhpStreamIpc\Message\Message;

$peer = new IpcPeer();
$session = $peer->createStdioSession();

// Handle requests from parent
$session->onRequest(function(Message $msg, $session): Message {
    // Process the message from parent
    echo "Received from parent: {$msg->message}\n";
    
    // Send response back to parent
    return new LogMessage("Hello from child!");
});

// Process messages until parent closes connection
$peer->tick();
```

## 🧩 Common Use Cases

### 1. Long-Running Background Process

Create a background process that regularly sends status updates:

```php
// backgroundWorker.php
use PhpStreamIpc\IpcPeer;
use PhpStreamIpc\Message\LogMessage;

$peer = new IpcPeer();
$session = $peer->createStdioSession();

// Simulate background work
for ($i = 1; $i <= 5; $i++) {
    // Do some work...
    sleep(1);
    
    // Send status update to parent
    $session->notify(new LogMessage("Progress: {$i}/5 complete", "info"));
}

// Send final success message
$session->notify(new LogMessage("Task completed successfully", "success"));
```

```php
// monitor.php
use PhpStreamIpc\IpcPeer;
use PhpStreamIpc\Message\LogMessage;
use PhpStreamIpc\Message\Message;

$descriptors = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w']
];
$process = proc_open('php backgroundWorker.php', $descriptors, $pipes);
[$stdin, $stdout, $stderr] = $pipes;

$peer = new IpcPeer();
$session = $peer->createStreamSession($stdin, $stdout, $stderr);

// Listen for status updates
$session->onMessage(function(Message $msg) {
    if ($msg instanceof LogMessage) {
        echo "[{$msg->level}] {$msg->message}\n";
    }
});

// Keep processing messages until process exits
while (proc_get_status($process)['running']) {
    $peer->tick(0.1);
}

proc_close($process);
```

### 2. Request-Response Pattern with Progress Updates

```php
// server.php
use PhpStreamIpc\IpcPeer;
use PhpStreamIpc\Message\LogMessage;
use PhpStreamIpc\Message\Message;

$peer = new IpcPeer();
$session = $peer->createStdioSession();

$session->onRequest(function(Message $msg, $session): Message {
    // Start processing request
    $session->notify(new LogMessage("Starting work", "info"));
    
    // Simulate work with progress updates
    for ($i = 1; $i <= 3; $i++) {
        sleep(1);
        $session->notify(new LogMessage("Progress: {$i}/3", "info"));
    }
    
    // Return final result
    return new LogMessage("Task complete!", "success");
});

$peer->tick();
```

```php
// client.php
use PhpStreamIpc\IpcPeer;
use PhpStreamIpc\Message\LogMessage;
use PhpStreamIpc\Message\Message;

$descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
$process = proc_open('php server.php', $descriptors, $pipes);
[$stdin, $stdout, $stderr] = $pipes;

$peer = new IpcPeer();
$session = $peer->createStreamSession($stdin, $stdout, $stderr);

// Listen for progress notifications
$session->onMessage(function(Message $msg) {
    if ($msg instanceof LogMessage) {
        echo "Progress: {$msg->message}\n";
    }
});

// Send request and wait for final response
echo "Sending request...\n";
$response = $session->request(new LogMessage("Start processing"), 10.0);
echo "Final response: {$response->message}\n";

proc_close($process);
```

### 3. Multiple Parallel Workers

```php
// manager.php
use PhpStreamIpc\IpcPeer;
use PhpStreamIpc\Message\LogMessage;
use PhpStreamIpc\Message\Message;

// Create IPC peer
$peer = new IpcPeer();
$sessions = [];
$workers = [];

// Launch multiple worker processes
for ($i = 1; $i <= 3; $i++) {
    $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $process = proc_open("php worker.php {$i}", $descriptors, $pipes);
    [$stdin, $stdout, $stderr] = $pipes;
    
    $session = $peer->createStreamSession($stdin, $stdout, $stderr);
    
    // Store session and process
    $sessions[$i] = $session;
    $workers[$i] = $process;
    
    // Listen for messages from this worker
    $session->onMessage(function(Message $msg) use ($i) {
        if ($msg instanceof LogMessage) {
            echo "Worker {$i}: {$msg->message}\n";
        }
    });
}

// Assign tasks to workers
foreach ($sessions as $id => $session) {
    $session->request(new LogMessage("Process task {$id}"), 5.0);
}

// Clean up
foreach ($workers as $process) {
    proc_close($process);
}
```

```php
// worker.php
use PhpStreamIpc\IpcPeer;
use PhpStreamIpc\Message\LogMessage;
use PhpStreamIpc\Message\Message;

$peer = new IpcPeer();
$session = $peer->createStdioSession();

// Get worker ID from command line
$workerId = $argv[1] ?? 'unknown';

// Handle task requests
$session->onRequest(function(Message $msg, $session) use ($workerId): Message {
    // Send some progress notifications
    $session->notify(new LogMessage("Worker {$workerId} starting task"));
    sleep(1);
    $session->notify(new LogMessage("Worker {$workerId} halfway done"));
    sleep(1);
    
    // Return final result
    return new LogMessage("Worker {$workerId} completed task");
});

$peer->tick();
```

### 4. Custom Message Types

Define custom message types by implementing the `Message` interface:

```php
// TaskMessage.php
namespace App\Messages;

use PhpStreamIpc\Message\Message;

final readonly class TaskMessage implements Message
{
    public function __construct(
        public string $action,
        public array $parameters = []
    ) {
    }
}
```

```php
// usage.php
use PhpStreamIpc\IpcPeer;
use App\Messages\TaskMessage;

$peer = new IpcPeer();
$session = $peer->createStdioSession();

// Send a custom task message
$task = new TaskMessage('processFile', [
    'filename' => 'data.csv',
    'columns' => ['name', 'email', 'age']
]);

$session->notify($task);
// Or make a request with the custom message
$response = $session->request($task)->await();
```

### 5. Handling Timeouts

```php
// client.php
use PhpStreamIpc\IpcPeer;
use PhpStreamIpc\Message\LogMessage;

$peer = new IpcPeer();
$session = $peer->createStdioSession();

try {
    // Set a short timeout (2 seconds)
    $response = $session->request(new LogMessage("Fast request"), 2.0)->await();
    echo "Received response: {$response->message}\n";
} catch (\PhpStreamIpc\Transport\TimeoutException $e) {
    echo "Request timed out: {$e->getMessage()}\n";
    // Handle timeout situation
}
```

## 🔄 Event-Driven Architecture

PHP Stream IPC uses an event-driven model where you can register handlers for different types of events:

```php
// Register a handler for notifications
$session->onMessage(function(Message $msg, IpcSession $session) {
    if ($msg instanceof LogMessage) {
        echo "[{$msg->level}] {$msg->message}\n";
    }
});

// Register a handler for requests
$session->onRequest(function(Message $msg, IpcSession $session): ?Message {
    // Process request
    if ($msg instanceof LogMessage) {
        // Return a response
        return new LogMessage("Processed: {$msg->message}");
    }
    
    // Return null if this handler can't process the request
    return null;
});
```

## 🔋 Advanced Configuration

### Custom Serialization

```php
use PhpStreamIpc\IpcPeer;
use PhpStreamIpc\Serialization\JsonMessageSerializer;

// Create a peer with custom serializer
$peer = new IpcPeer(
    new JsonMessageSerializer()
);

$session = $peer->createStdioSession();
```

### Custom Request ID Generation

```php
use PhpStreamIpc\IpcPeer;
use PhpStreamIpc\Envelope\Id\RequestIdGenerator;

class UuidRequestIdGenerator implements RequestIdGenerator
{
    public function generate(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}

// Create peer with custom ID generator
$peer = new IpcPeer(
    null, // use default serializer
    new UuidRequestIdGenerator()
);
```

## 📚 Understanding the Message Flow

1. **Direct Notifications**: Send messages from one process to another with `notify()`
2. **Request-Response**: Send a request with `request()` and get a correlated response
3. **Progress Updates**: A process can send notifications while processing a request
4. **Event Handling**: Register callbacks for messages and requests with `onMessage()` and `onRequest()`

## 🤝 Contributing

`amphp/byte-stream` support has not been tested enough. In the meantime, contributions are welcome! Please feel free to submit a Pull Request.

I want to keep the library pretty thin, but extendable. If you have a use case that you think would be useful, please open an issue and I'll see what I can do.

I am open to breaking changes if they are needed to make the library more flexible.


## 📄 License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
