# 🚀 **PHP Stream IPC**: Simple, Reliable Inter-Process Communication in Pure PHP

[![Packagist Version](https://img.shields.io/packagist/v/riki137/stream-ipc.svg)](https://packagist.org/packages/riki137/stream-ipc)
[![Code Coverage](https://codecov.io/gh/riki137/php-stream-ipc/branch/main/graph/badge.svg)](https://codecov.io/gh/riki137/stream-ipc)
[![GitHub Tests](https://github.com/riki137/php-stream-ipc/actions/workflows/tests.yml/badge.svg)](https://github.com/riki137/php-stream-ipc/actions/workflows/tests.yml)
[![PHPStan Level 8](https://img.shields.io/badge/PHPStan-Level%208-brightgreen.svg)](https://github.com/phpstan/phpstan)
[![PHP Version](https://img.shields.io/badge/php-8.2%2B-8892BF.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-MIT-yellow.svg)](LICENSE)

PHP Stream IPC is a **lightweight, zero-dependency** PHP library designed for robust **IPC** (inter-process communication) **through streams, pipes, sockets, and standard I/O**. Whether you're managing background jobs, orchestrating parallel tasks, or simply need efficient communication between PHP processes, PHP Stream IPC makes it straightforward, reliable, and fast.

Forget complicated setups or bloated frameworks—this library is pure PHP, requiring **no external dependencies**, and seamlessly integrates with native PHP streams, Symfony's popular `Process` component or AMPHP's ByteStream component (or your own adapter). It handles everything from framing messages to correlating requests and responses, enabling your applications to effortlessly communicate in real time.

### 🔥 **Why choose PHP Stream IPC?**

* **Zero Dependencies**: Lightweight, pure PHP—installs fast and clean.
* **Reliable Messaging**: Automatic message framing ensures data integrity.
* **Performance-Focused**: Built for speed and efficiency. You can send hundreds of messages per second.
* **Built-in Request-Response Handling**: Easily correlate requests with their responses, simplifying async communication.
* **Flexible Serialization**: Fast Native PHP serialization by default, with JSON support ready out of the box.
* **Easy Integration with Symfony/AMPHP**: Fits perfectly into your existing workflow.
* **Real-time Notifications and Updates**: Effortlessly handle real-time progress updates and event-driven messaging.
* **Error and Timeout Management**: Robust exception handling, graceful stream closure management, and built-in timeout control keep your processes resilient.
* **Extendable by Design**: Simple interfaces and clearly defined contracts mean you can easily adapt or extend functionality for your specific needs.

Whether you're building scalable PHP services, handling parallel background processing, or connecting multiple PHP scripts reliably, PHP Stream IPC gives you the control and simplicity you've been looking for.

---

## 📦 Quick Installation

Install with Composer in seconds:

```bash
composer require riki137/stream-ipc
```

---


## 📦 Quick Installation

Install via Composer:

```bash
composer require riki137/stream-ipc
```

---

## ⚡ Quick Usage Example

### Parent-Child IPC Example:

#### Parent (`parent.php`):

```php
use StreamIpc\NativeIpcPeer;
use StreamIpc\Message\LogMessage;

$process = proc_open('php child.php', [
    ['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']
], $pipes);

$peer = new NativeIpcPeer();
$session = $peer->createStreamSession($pipes[0], $pipes[1], $pipes[2]);

$response = $session->request(new LogMessage('Ping!'))->await();
echo "Child responded: {$response->message}\n";

proc_close($process);
```

#### Child (`child.php`):

```php
use StreamIpc\NativeIpcPeer;
use StreamIpc\Message\LogMessage;
use StreamIpc\Message\Message;

$peer = new NativeIpcPeer();
$session = $peer->createStdioSession();

$session->onRequest(fn(Message $msg) => new LogMessage("Pong!"));
$peer->tick();
```

---

## 📖 Common Use Cases

* **Background Tasks:** Run asynchronous workers with real-time communication.
* **Multi-Process PHP Applications:** Efficiently manage parallel PHP scripts.
* **Real-time Progress Tracking:** Provide updates on task progress via IPC.
* **Server-Client PHP Scripts:** Use PHP scripts as IPC-driven microservices.

---

## 📚 Understanding the Message Flow

1. **Direct Notifications**: Send messages from one process to another with `notify()`
2. **Request-Response**: Send a request with `request()` and get a correlated response
3. **Progress Updates**: A process can send notifications while processing a request
4. **Event Handling**: Register callbacks for messages and requests with `onMessage()` and `onRequest()`

---

### 🔄 Message Handling

Register event-driven handlers easily:

```php
// Notification Handler
$session->onMessage(function (Message $msg) {
    echo "Received: {$msg->message}\n";
});

// Request Handler with Response
$session->onRequest(function (Message $msg): ?Message {
    return new LogMessage("Processed request: {$msg->message}");
});
```

### ⏳ Timeout and Exception Management

Requests automatically use a 30 second timeout if you don't specify one.
Handle request timeouts gracefully:

```php
use StreamIpc\Transport\TimeoutException;

try {
    $session->request(new LogMessage("Quick task"), 3.0)->await();
} catch (TimeoutException $e) {
    echo "Task timed out: {$e->getMessage()}\n";
}
```

### 🎛 Advanced Configuration

#### Custom Serialization (JSON):

```php
use StreamIpc\Serialization\JsonMessageSerializer;
$peer = new NativeIpcPeer(new JsonMessageSerializer());
```

#### Custom Request ID Generation (UUID):

```php
use StreamIpc\Envelope\Id\RequestIdGenerator;

class UuidRequestIdGenerator implements RequestIdGenerator {
    public function generate(): string {
        return bin2hex(random_bytes(16));
    }
}

$peer = new NativeIpcPeer(null, new UuidRequestIdGenerator());
```

---

## 🛠 Development & Contribution

Contributions are welcome! To contribute:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/my-feature`)
3. Commit changes (`git commit -m "Add my feature"`)
4. Push your branch (`git push origin feature/my-feature`)
5. Open a Pull Request on GitHub

**Current areas open for contributions:**

* Enhancing AMPHP transport stability and tests
* Improving error handling documentation and examples

---

## 📄 License

PHP Stream IPC is open-source software licensed under the **MIT License**. See [LICENSE](LICENSE) for more details.

---

## 📈 SEO Keywords

**IPC PHP, PHP inter-process communication, PHP streams, PHP IPC library, IPC pipes, Symfony Process IPC, asynchronous PHP, PHP messaging, PHP IPC example, PHP parallel processing**

---

## 📌 Tags

`php`, `ipc`, `symfony-process`, `stream`, `asynchronous`, `inter-process communication`, `message passing`, `php-library`, `ipc-framework`

---

> For issues, feature requests, or general inquiries, please [open an issue](https://github.com/riki137/stream-ipc/issues).

---

© [riki137](https://github.com/riki137)


## 🧩 Documentation

### Using Symfony's Process Component

The library works seamlessly with Symfony's `Process` component. The
`createSymfonyProcessSession()` helper automatically starts the process
and wires it for message passing using a Symfony `InputStream`. Configure
your `Process` instance (working directory, environment variables, timeouts,
etc.) before handing it to the session:

```php
use Symfony\Component\Process\Process;

$process = new Process([PHP_BINARY, 'child.php']);
$process->setTimeout(0); // disable Process timeouts if desired
$peer = new SymfonyIpcPeer();
$session = $peer->createSymfonyProcessSession($process);

$response = $session->request(new LogMessage('Hello from parent!'), 5.0)->await();
echo "Child responded: {$response->message}\n";
```

You may run multiple processes in parallel and drive them all by calling
`$peer->tick()` (or `tickFor()`) inside your main loop.

This approach requires the `symfony/process` package:

```bash
composer require symfony/process
```

```php
// child.php
use StreamIpc\NativeIpcPeer;
use StreamIpc\Message\LogMessage;
use StreamIpc\Message\Message;

$peer = new NativeIpcPeer();
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


### Long-Running Background Process

Create a background process that regularly sends status updates:

```php
// backgroundWorker.php
use StreamIpc\NativeIpcPeer;
use StreamIpc\Message\LogMessage;

 $peer = new NativeIpcPeer();
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
use StreamIpc\NativeIpcPeer;
use StreamIpc\Message\LogMessage;
use StreamIpc\Message\Message;

$descriptors = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w']
];
$process = proc_open('php backgroundWorker.php', $descriptors, $pipes);
[$stdin, $stdout, $stderr] = $pipes;

 $peer = new NativeIpcPeer();
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

### Request-Response Pattern with Progress Updates

```php
// server.php
use StreamIpc\NativeIpcPeer;
use StreamIpc\Message\LogMessage;
use StreamIpc\Message\Message;

 $peer = new NativeIpcPeer();
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
use StreamIpc\NativeIpcPeer;
use StreamIpc\Message\LogMessage;
use StreamIpc\Message\Message;

$descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
$process = proc_open('php server.php', $descriptors, $pipes);
[$stdin, $stdout, $stderr] = $pipes;

 $peer = new NativeIpcPeer();
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

### Multiple Parallel Workers

```php
// manager.php
use StreamIpc\NativeIpcPeer;
use StreamIpc\Message\LogMessage;
use StreamIpc\Message\Message;

// Create IPC peer
 $peer = new NativeIpcPeer();
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
use StreamIpc\NativeIpcPeer;
use StreamIpc\Message\LogMessage;
use StreamIpc\Message\Message;

 $peer = new NativeIpcPeer();
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

### Custom Message Types

Define custom message types by implementing the `Message` interface:

```php
// TaskMessage.php
namespace App\Messages;

use StreamIpc\Message\Message;

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
use StreamIpc\NativeIpcPeer;
use App\Messages\TaskMessage;

 $peer = new NativeIpcPeer();
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

### Handling Timeouts

```php
// client.php
use StreamIpc\NativeIpcPeer;
use StreamIpc\Message\LogMessage;

 $peer = new NativeIpcPeer();
$session = $peer->createStdioSession();

try {
    // Set a short timeout (2 seconds)
    $response = $session->request(new LogMessage("Fast request"), 2.0)->await();
    echo "Received response: {$response->message}\n";
} catch (\StreamIpc\Transport\TimeoutException $e) {
    echo "Request timed out: {$e->getMessage()}\n";
    // Handle timeout situation
}
```

### 🔄 Event-Driven Architecture

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

### 🔋 Advanced Configuration

#### Custom Serialization

```php
use StreamIpc\NativeIpcPeer;
use StreamIpc\Serialization\JsonMessageSerializer;

// Create a peer with custom serializer
$peer = new NativeIpcPeer(
    new JsonMessageSerializer()
);

$session = $peer->createStdioSession();
```

#### Custom Request ID Generation

```php
use StreamIpc\NativeIpcPeer;
use StreamIpc\Envelope\Id\RequestIdGenerator;

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
$peer = new NativeIpcPeer(
    null, // use default serializer
    new UuidRequestIdGenerator()
);
```
