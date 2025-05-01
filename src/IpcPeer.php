<?php
declare(strict_types=1);

namespace PhpStreamIpc;

use Amp\ByteStream\ReadableResourceStream;
use Amp\ByteStream\WritableResourceStream;
use Amp\Cancellation;
use Amp\Process\Process;
use Closure;
use PhpStreamIpc\Message\Message;
use PhpStreamIpc\Envelope\Id\PidHrtimeRequestIdGenerator;
use PhpStreamIpc\Envelope\Id\RequestIdGenerator;
use PhpStreamIpc\Serialization\MessageSerializer;
use PhpStreamIpc\Serialization\NativeMessageSerializer;
use PhpStreamIpc\Transport\DataReader;
use PhpStreamIpc\Transport\DataSender;
use PhpStreamIpc\Transport\MessageCommunicator;
use PhpStreamIpc\Transport\StreamDataReader;
use PhpStreamIpc\Transport\StreamDataSender;
use function Amp\ByteStream\getStdin;
use function Amp\ByteStream\getStdout;

/**
 * Entry point for Inter-Process Communication.
 * Manages one or more IpcSession instances (sync or async) over arbitrary streams.
 *
 * Example:
 * ```php
 * $peer = new IpcPeer(async: true);
 * $session = $peer->connectToStdio();
 * $session->onMessage(fn(LogMessage $m) => file_put_contents('log.txt', $m->message));
 */
final class IpcPeer
{
    /**
     * Default timeout for request/response in seconds.
     */
    public const DEFAULT_TIMEOUT = 0.5;

    /** @var IpcSession[] Active sessions created by this peer */
    private array $sessions = [];

    /** @var Closure Factory for sync or async session instances */
    private readonly Closure $sessionFactory;

    /**
     * @param bool $async When true, use AsyncIpcSession; otherwise SyncIpcSession.
     * @param MessageSerializer $serializer Serializer to encode/decode Message objects.
     * @param RequestIdGenerator $idGen Generates unique IDs for requests.
     * @param float $timeout Timeout (in seconds) for request() before failure. Defaults to 0.5 because stdio should be very fast.
     * @param Cancellation|null $cancellation Optional cancellation token for async sessions.
     */
    public function __construct(
        bool $async = true,
        private readonly MessageSerializer $serializer = new NativeMessageSerializer(),
        private readonly RequestIdGenerator $idGen = new PidHrtimeRequestIdGenerator(),
        private readonly float $timeout = self::DEFAULT_TIMEOUT,
        private readonly ?Cancellation $cancellation = null,
    ) {
        $this->sessionFactory = $async
            ? $this->createAsyncSession(...)
            : $this->createSyncSession(...);
    }

    /**
     * @internal
     */
    private function createSyncSession(DataSender $sender, DataReader $reader): SyncIpcSession
    {
        return new SyncIpcSession(
            new MessageCommunicator($reader, $sender, $this->serializer),
            $this->idGen,
            $this->timeout,
        );
    }

    /**
     * @internal
     */
    private function createAsyncSession(DataSender $sender, DataReader $reader): AsyncIpcSession
    {
        return new AsyncIpcSession(
            new MessageCommunicator($reader, $sender, $this->serializer),
            $this->idGen,
            $this->timeout,
            $this->cancellation,
        );
    }

    /**
     * Instantiate a new IpcSession on the provided streams.
     *
     * @param DataSender $sender Lower-level sender (e.g. StreamDataSender).
     * @param DataReader $reader Lower-level reader (e.g. StreamDataReader).
     * @return IpcSession A new session you can notify(), request(), etc.
     */
    public function createSession(DataSender $sender, DataReader $reader): IpcSession
    {
        $session = ($this->sessionFactory)($reader, $sender);
        $this->sessions[] = $session;
        return $session;
    }

    /**
     * Shortcut to create a session over PHP streams (stdio, pipes).
     *
     * @param WritableResourceStream $write
     * @param ReadableResourceStream $read
     * @param ReadableResourceStream|null $read2 Optionally a second read stream (e.g. stderr).
     * @return IpcSession
     */
    public function createStreamSession(
        WritableResourceStream $write,
        ReadableResourceStream $read,
        ?ReadableResourceStream $read2 = null,
    ): IpcSession {
        return $this->createSession(
            new StreamDataSender($write),
            new StreamDataReader($read, $read2),
        );
    }

    /**
     * Bind a session to the current process’s STDIN/STDOUT.
     *
     * @return IpcSession
     */
    public function createStdioSession(): IpcSession
    {
        return $this->createStreamSession(getStdout(), getStdin());
    }

    /**
     * Connect to an AMPHP child Process over its stdio/stderr.
     *
     * @param Process $proc
     * @return IpcSession
     */
    public function createProcessSession(Process $proc): IpcSession
    {
        return $this->createStreamSession(
            $proc->getStdin(),
            $proc->getStdout(),
            $proc->getStderr(),
        );
    }

    /**
     * Send a notification message to **all** active sessions.
     *
     * @param Message $msg The notification to broadcast.
     */
    public function broadcast(Message $msg): void
    {
        foreach ($this->sessions as $sess) {
            $sess->notify($msg);
        }
    }
}
