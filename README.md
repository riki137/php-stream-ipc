# Async Stream IPC for PHP  
Lightweight, high-throughput, single-dependency (AMPHP) inter-process communication via STDIO or any PHP stream.

Built for efficiency and developer joy. Runs on [amphp](https://amphp.org/) with a clean interface for async and sync scenarios.

## ✨ Features

- 🔄 Bi-directional request/response support with automatic timeout handling
- 📣 One-way notifications (fire-and-forget)
- 🧵 Works with any stream (stdio, pipes, sockets, etc.)
- 🧠 Full async and sync support via pluggable session types
- 🧩 Flexible message serialization (native PHP or JSON)
- 🧪 Zero dependencies outside of AMPHP libraries

---

## 💡 Use Case

Need to spawn a child PHP process and talk to it over `STDIN`/`STDOUT` using structured messages? `php-stream-ipc` lets you:

- Send structured messages with auto-generated request IDs
- Handle incoming notifications or requests with your own logic
- Compose high-level protocols without boilerplate

---

## 🚀 Quick Start

#### 1. Install

```bash
composer require riki137/php-stream-ipc
```

---

#### 2. Define Your Messages

All messages must implement the `PhpStreamIpc\Message\Message` interface.

```php
use PhpStreamIpc\Message\Message;

final class SayHello implements Message {
    public function __construct(public string $name) {}
}
```

---

#### 3. Child Process (Server Side)

```php
use PhpStreamIpc\IpcPeer;
use PhpStreamIpc\Message\LogMessage;

$peer = new IpcPeer(); // defaults to async + native PHP serialization
$session = $peer->createStdioSession();

$session->onRequest(function (SayHello $msg) {
    return new LogMessage("Hello, {$msg->name}!"),
});
```

---

#### 4. Parent Process (Client Side)

```php
use Amp\Process\Process;
use PhpStreamIpc\IpcPeer;
use PhpStreamIpc\Message\LogMessage;

$proc = Process::start(['php', 'child-script.php']);
$peer = new IpcPeer(); // async mode
$session = $peer->createProcessSession($proc);

// Fire a request
$response = $session->request(new SayHello('Riki'));

if ($response instanceof LogMessage) {
    echo "Child said: {$response->message}";
}
```

---

## 🧠 Concepts

### Sessions: `IpcSession`

Provides:
- `notify(Message)` – fire-and-forget
- `request(Message): Future<Message>` – wait for reply
- `onMessage()` and `onRequest()` handlers

Choose between:
- `SyncIpcSession` – blocks until reply, good for CLI loops
- `AsyncIpcSession` – non-blocking because of Revolt AMPHP event loop

---

### Message Serialization

Out-of-the-box:
- `NativeMessageSerializer` – `serialize()` + `base64_encode()` (to avoid newlines in output)
- `JsonMessageSerializer` – full introspective reflection, readable

Switch via `IpcPeer` constructor:

```php
new IpcPeer(async: false, serializer: new JsonMessageSerializer());
```

---

## 🧪 Testing

Full unit tests will be added soon.

---

## 🛠️ Internals (Advanced)

- Messages are wrapped in `RequestEnvelope` or `ResponseEnvelope` for routing
- `PidHrtimeRequestIdGenerator` ensures globally unique request IDs
- `MessageCommunicator` serializes and routes messages via AMP-readable streams
- Cancellation & timeouts handled via AMPHP `Cancellation`/`DeferredFuture`

---

## 📜 License

MIT – do what you want, just don’t blame me when your printer becomes sentient.

---

## 🧠 Brainstorm

- Want structured schemas? Integrate something like `symfony/serializer` or `jms/serializer`.
- Need persistent, long-running processes? Consider watchdog logic for `Process`.
- Looking for integration with sockets or WebSockets? You could plug in custom `DataSender`/`DataReader` easily.
- Want to monitor logs live? Create a `LogStreamMessage` type and broadcast to all active sessions.

Happy hacking! 🔥
