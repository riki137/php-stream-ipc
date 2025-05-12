# php-stream-ipc – Asynchronous Stream IPC for PHP

[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)  
[![Packagist Version](https://img.shields.io/packagist/v/riki137/php-stream-ipc)](https://packagist.org/packages/riki137/php-stream-ipc)  
[![PHP 8.2+](https://img.shields.io/badge/php-^8.2-8892BF.svg)](https://www.php.net/)  

Asynchronous, dependency-free AMPHP-based library for high-throughput, low-latency inter-process communication over stdio, sockets and custom streams.

## Features
- **Zero dependencies**: ships without extra PHP extensions
- **High throughput**: leverages AMPHP's event loop
- **Low latency**: optimized for performance
- **Flexible transports**: stdio, sockets, custom streams
- **Patterns supported**: request–response, notifications, broadcast

## Requirements
- PHP ^8.2
- amphp/amp ^3.1

## Installation
```bash
composer require riki137/php-stream-ipc
```

## Example: Master ↔ Multiple Slaves (Request–Response)

In this example, a **master** process dispatches named-jobs to multiple **slave** processes and collects their results, all over stdio.

---

### 1. Define your messages

Put these in, for example, `src/Message/TaskRequest.php` and `src/Message/TaskResponse.php`:

```php
<?php
declare(strict_types=1);

namespace App\Message;

use PhpStreamIpc\Message\Message;

final class TaskRequest implements Message
{
    public function __construct(public string $job) {}
}
```

```php
<?php
declare(strict_types=1);

namespace App\Message;

use PhpStreamIpc\Message\Message;

final class TaskResponse implements Message
{
    public function __construct(public string $result) {}
}
```

---

### 2. Master (`master.php`)

```php
<?php
declare(strict_types=1);

use Amp\Process\Process;
use function Amp\delay;
use function Amp\Future\await;
use PhpStreamIpc\IpcPeer;
use PhpStreamIpc\Message\LogMessage;
use App\Message\TaskRequest;
use App\Message\TaskResponse;

require 'vendor/autoload.php';

// 1) Prepare the IPC peer
$peer = new IpcPeer();

// 2) Spawn and connect to N slaves
$slaveCount = 3;
$sessions   = [];

for ($i = 0; $i < $slaveCount; $i++) {
    $proc = new Process(['php', 'slave.php']);
    $proc->start();
    $sessions[] = $peer->createProcessSession($proc);
}

// 3) Send each slave a TaskRequest and await its TaskResponse
foreach ($sessions as $index => $session) {
    $request  = new TaskRequest("Task #{$index}");
    /** @var TaskResponse $response */
    $response = $session->request($request)->await();
    echo "Slave {$index} replied with: {$response->result}\n";
    
    // If you need just one-way communication, you can also use $session->notify()
    $session->notify(new LogMessage("Slave {$index} finished", "info"));
}

// 4) Give slaves a moment, then tell them to shut down
await(delay(500));
$peer->broadcast(new LogMessage('shutdown', 'info'));
```

---

### 3. Slave (`slave.php`)

```php
<?php
declare(strict_types=1);

use PhpStreamIpc\IpcPeer;
use PhpStreamIpc\Message\LogMessage;
use App\Message\TaskRequest;
use App\Message\TaskResponse;

require 'vendor/autoload.php';

// 1) Create IPC session over stdio
$peer    = new IpcPeer();
$session = $peer->createStdioSession();

// 2) Handle TaskRequest → TaskResponse
$session->onRequest(function (TaskRequest $msg) {
    // Do the “work”
    $result = strtoupper($msg->job);
    return new TaskResponse($result);
});

// 3) Listen for broadcast shutdown and exit
$session->onMessage(function (LogMessage $msg) {
    if ($msg->message === 'shutdown') {
        exit(0);
    }
});

// 4) Manually pump the loop; you can also mix in other work here
while (true) {
    $session->tick();
   
    // OR to receive notifications:
    $notification = $session->receive();
    if ($notification instanceof LogMessage) {
        echo "Received notification: {$notification->message}\n";
    }
}
```

---

## Customizing

* **Serializer**
  Swap in JSON encoding and your own ID generator:

  ```php
  new IpcPeer(
    new JsonMessageSerializer(),
    new PidHrtimeRequestIdGenerator()
  );
  ```

## Contributing
Contributions, issues and feature requests are welcome!
I am trying to keep this library minimal, easy to use and intuitive, with a single purpose.

## License

MIT © Richard Popelis
