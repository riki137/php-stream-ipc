# 🚀 **PHP Stream IPC**: Simple, Reliable Inter-Process Communication in Pure PHP

[![Packagist Version](https://img.shields.io/packagist/v/riki137/stream-ipc.svg)](https://packagist.org/packages/riki137/stream-ipc)
[![Code Coverage](https://codecov.io/gh/riki137/php-stream-ipc/branch/main/graph/badge.svg)](https://codecov.io/gh/riki137/php-stream-ipc)
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

## ⚡ 30-Second Tour

The fastest way to grok the API is to copy-paste the two files below,
run `php parent.php`, and watch “Pong!” come back from a child process.

<details>
<summary><strong>parent.php – ask, await, done</strong></summary>

```php
<?php
use StreamIpc\NativeIpcPeer;
use StreamIpc\Message\LogMessage;

$peer    = new NativeIpcPeer();                       // ① create peer
$cmd     = proc_open('php child.php',                 // ② launch child
             [['pipe','r'], ['pipe','w'], ['pipe','w']], $pipes);

$session = $peer->createStreamSession(...$pipes);     // ③ wrap its pipes
$reply   = $session->request(new LogMessage('Ping!')) // ④ send request
                   ->await();                         // ⑤ wait (should be extremely fast)

echo "Child said: {$reply->message}\n";               // ⑥ print response
```

</details>

<details>
<summary><strong>child.php – listen & respond</strong></summary>

```php
<?php
use StreamIpc\NativeIpcPeer;
use StreamIpc\Message\LogMessage;
use StreamIpc\Message\Message;

$peer    = new NativeIpcPeer();
$session = $peer->createStdioSession();               // ① wires STDIN/OUT

$session->onRequest(fn(Message $m)                    // ② one-liner handler
    => new LogMessage("Pong!"));                      // ③ respond

$peer->tick();                                        // ④ process once
```

</details>

That’s the entire request/response stack – no frameworks, no bootstrapping,
just PHP streams and a sprinkle of *Stream IPC* sugar.

---

## 🛠 Everyday Patterns

*Need progress bars, fire-and-forget notifications, or multiple workers?*
These snippets are battle-tested shortcuts.

<details>
<summary><strong>Fire off notifications (no response expected)</strong></summary>

```php
$session->notify(new LogMessage('Build started...'));
```

</details>

<details>
<summary><strong>Progress reporting while working on a request</strong></summary>

```php
$session->onRequest(function (Message $req, IpcSession $session) {
    for ($i = 1; $i <= 3; $i++) {
        $session->notify(new LogMessage("Step $i/3 done"));
        sleep(1);
    }
    return new LogMessage('All steps complete ✅');
});
```

</details>

<details>
<summary><strong>Spawning N parallel workers</strong></summary>

```php
$workers = [];
for ($i = 1; $i <= 4; $i++) {
    $proc          = proc_open("php worker.php $i", [['pipe','r'],['pipe','w'],['pipe','w']], $p);
    $workers[$i]   = $peer->createStreamSession(...$p);
    $workers[$i]->onMessage(fn(Message $m) => printf("[W#%d] %s\n", $i, $m->message));
}
```

</details>

---

## 🚀 Advanced Patterns

When the basics feel too tame, dip into these **expandable sections** for
in-depth scenarios. They’re long, so they stay folded until summoned.

<details>
<summary>⚡ **Symfony Process transport** – zero-copy I/O, built-in timeout</summary>

```php
use Symfony\Component\Process\Process;
use StreamIpc\SymfonyIpcPeer;
use StreamIpc\Message\LogMessage;

$process  = new Process([PHP_BINARY, 'child.php']);
$peer     = new SymfonyIpcPeer();
$session  = $peer->createSymfonyProcessSession($process);

$response = $session->request(new LogMessage('Hello 👋'), 5.0)->await();
echo $response->message;
```

</details>

<details>
<summary>🔌 **AMPHP ByteStream transport** – async, event-loop friendly</summary>

```php
use Amp\ByteStream\ResourceInputStream;
use Amp\ByteStream\ResourceOutputStream;
use StreamIpc\AmphpIpcPeer;
use StreamIpc\Message\LogMessage;

[$r1,$w1] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
[$r2,$w2] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);

$peer     = new AmphpIpcPeer();
$session  = $peer->createByteStreamSession(
    new WritableResourceStream($w1),
    [new ReadableResourceStream($r2)]
);

$session->notify(new LogMessage('Async says hi!'));
$peer->tick();      // Amp’s EventLoop will drive this in real life
```

</details>

<details>
<summary>🕹 **Custom serializers &\nbsp;ID generators**</summary>

```php
use StreamIpc\NativeIpcPeer;
use StreamIpc\Serialization\JsonMessageSerializer;
use StreamIpc\Envelope\Id\RequestIdGenerator;

// JSON on the wire
$peer = new NativeIpcPeer(new JsonMessageSerializer());

// 128-bit random IDs
class UuidGen implements RequestIdGenerator {
    public function generate(): string { return bin2hex(random_bytes(16)); }
}
$peer = new NativeIpcPeer(null, new UuidGen());
```

</details>

---

## 📚 Deep-Dive Cookbook

> Each pattern below is a **self-contained, runnable demo**.
> Put the files in the same directory, run the “driver” script (`php client.php`, `php manager.php`, …) and watch the messages fly.

<details>
<summary><strong>📖 Request ⇄ Response <em>with live progress updates</em></strong></summary>

### server.php – the worker that streams progress then replies

```php
<?php
use StreamIpc\NativeIpcPeer;
use StreamIpc\Message\LogMessage;
use StreamIpc\Message\Message;

$peer    = new NativeIpcPeer();
$session = $peer->createStdioSession();

/** Answer every request with 3 progress pings + a final success */
$session->onRequest(function (Message $req, $session): Message {
    for ($i = 1; $i <= 3; $i++) {
        $session->notify(new LogMessage("Progress $i / 3"));
        sleep(1);
    }
    return new LogMessage('✅  Finished all work');
});

$peer->tick();   // block until parent closes streams
```

### client.php – the caller that shows progress in real-time

```php
<?php
use StreamIpc\NativeIpcPeer;
use StreamIpc\Message\LogMessage;
use StreamIpc\Message\Message;

$proc = proc_open('php server.php',
    [['pipe','r'], ['pipe','w'], ['pipe','w']], $pipes);

$peer    = new NativeIpcPeer();
$session = $peer->createStreamSession(...$pipes);

/** Show every notification immediately */
$session->onMessage(function (Message $m) {
    if ($m instanceof LogMessage) {
        echo "[update] {$m->message}\n";
    }
});

echo "→ sending job …\n";
$final = $session->request(new LogMessage('Start!'), 10)->await();
echo "→ DONE: {$final->message}\n";

proc_close($proc);
```

</details>

---

<details>
<summary><strong>⛏ Long-running background worker <em>that streams status</em></strong></summary>

### backgroundWorker.php

```php
<?php
use StreamIpc\NativeIpcPeer;
use StreamIpc\Message\LogMessage;

$peer    = new NativeIpcPeer();
$session = $peer->createStdioSession();

for ($i = 1; $i <= 5; $i++) {
    sleep(1);
    $session->notify(new LogMessage("Step $i/5 complete"));
}

$session->notify(new LogMessage('🎉  Task finished', 'success'));
```

### monitor.php

```php
<?php
use StreamIpc\NativeIpcPeer;
use StreamIpc\Message\LogMessage;
use StreamIpc\Message\Message;

$proc  = proc_open('php backgroundWorker.php',
    [['pipe','r'], ['pipe','w'], ['pipe','w']], $pipes);

$peer    = new NativeIpcPeer();
$session = $peer->createStreamSession(...$pipes);

$session->onMessage(function (Message $m) {
    if ($m instanceof LogMessage) {
        printf("[%s] %s\n", strtoupper($m->level), $m->message);
    }
});

while (proc_get_status($proc)['running']) {
    $peer->tick(0.1);      // non-blocking poll
}

proc_close($proc);
```

</details>

---

<details>
<summary><strong>👷‍♀️ Multi-tenant task manager <em>(parallel workers)</em></strong></summary>

### manager.php – spins up 3 workers and assigns jobs

```php
<?php
use StreamIpc\NativeIpcPeer;
use StreamIpc\Message\LogMessage;
use StreamIpc\Message\Message;

$peer      = new NativeIpcPeer();
$sessions  = [];
$processes = [];

/* Launch three child workers */
for ($id = 1; $id <= 3; $id++) {
    $p = proc_open("php worker.php $id",
        [['pipe','r'], ['pipe','w'], ['pipe','w']], $pipes);

    $sessions[$id]  = $peer->createStreamSession(...$pipes);
    $processes[$id] = $p;

    $sessions[$id]->onMessage(fn(Message $m)
        => printf("· Worker %d says: %s\n", $id, $m->message));
}

/* Fire one job at each worker */
foreach ($sessions as $id => $s) {
    $s->request(new LogMessage("Job for W$id"));
}

/* Pump until everybody is done */
while (array_filter($processes, fn($p) => proc_get_status($p)['running'])) {
    $peer->tick(0.05);
}

array_walk($processes, 'proc_close');
```

### worker.php – does its thing, streams updates, replies

```php
<?php
use StreamIpc\NativeIpcPeer;
use StreamIpc\Message\LogMessage;
use StreamIpc\Message\Message;

$wid     = $argv[1] ?? '?';
$peer    = new NativeIpcPeer();
$session = $peer->createStdioSession();

$session->onRequest(function (Message $m, $s) use ($wid): Message {
    $s->notify(new LogMessage("[$wid] starting"));
    sleep(1);
    $s->notify(new LogMessage("[$wid] halfway"));
    sleep(1);
    return new LogMessage("[$wid] done");
});

$peer->tick();
```

</details>

---

<details>
<summary><strong>🧩 Custom DTOs <em>(domain-specific messages)</em></strong></summary>

### src/TaskMessage.php – your own typed message

```php
<?php
namespace App\Messages;

use StreamIpc\Message\Message;

final readonly class TaskMessage implements Message
{
    public function __construct(
        public string $action,
        public array  $params = [],
    ) {}
}
```

### usage.php – sending custom messages

```php
<?php
use StreamIpc\NativeIpcPeer;
use App\Messages\TaskMessage;

$peer    = new NativeIpcPeer();
$session = $peer->createStdioSession();

/* Fire-and-forget notification */
$session->notify(new TaskMessage('reindex', ['db' => 'catalog']));

/* Or ask for a result */
$reply = $session->request(new TaskMessage('checksum', ['path' => '/dump.sql']))
                ->await();
var_dump($reply);
```

</details>

---

## 💡 Why choose **PHP Stream IPC**?

* **Zero Dependencies** – Installs in seconds; works on shared hosting.
* **Reliable Framing** – 4-byte magic + 32-bit length = no corrupted payloads.
* **Correlation Built-in** – Automatic promise matching (`request()->await()`).
* **Pluggable Serialization** – Native `serialize()`, JSON, or roll your own.
* **Graceful Timeouts & Errors** – Exceptions bubble exactly where you need them.
* **Symphony & AMPHP adapters** – Mix blocking and async worlds effortlessly.
* **Runs Everywhere** – Works on Linux, macOS, Windows, inside Docker, CI, etc.

---

## 🛣 Roadmap & Contributing

- Stability and Polishment
- More tests

PRs are welcome! Fork → branch → commit → pull-request.
Guidelines live in [`CONTRIBUTING.md`](CONTRIBUTING.md) – tests & PHPStan must pass.

---

## 📈 SEO Keywords

*IPC PHP, PHP inter-process communication, PHP streams, IPC pipes, Symfony Process IPC, asynchronous PHP, PHP messaging, PHP IPC example, parallel processing PHP*

---

© [**riki137**](https://github.com/riki137) • Licensed under MIT
