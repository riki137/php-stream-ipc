<?php
declare(strict_types=1);

namespace PhpStreamIpc;

use Amp\ByteStream\ReadableResourceStream;
use Amp\ByteStream\WritableResourceStream;
use Amp\Process\Process;
use PhpStreamIpc\Envelope\Id\PidHrtimeRequestIdGenerator;
use PhpStreamIpc\Envelope\Id\RequestIdGenerator;
use PhpStreamIpc\Message\Message;
use PhpStreamIpc\Serialization\MessageSerializer;
use PhpStreamIpc\Serialization\NativeMessageSerializer;
use PhpStreamIpc\Transport\FramedStreamMessageTransport;
use PhpStreamIpc\Transport\MessageTransport;
use function Amp\ByteStream\getStdin;
use function Amp\ByteStream\getStdout;

/**
 * Entry point for IPC operations, creating and managing multiple IpcSession instances over stdio, pipes, or child processes.
 * Enables broadcasting notifications, orchestrating request/response exchanges, and configuring custom serializers and ID generators.
 */
final class IpcPeer
{
    /**
     * Default timeout for request/response in seconds.
     */
    public const DEFAULT_TIMEOUT = 0.5;

    /** @var IpcSession[] Active sessions created by this peer */
    private array $sessions = [];

    /**
     * @param MessageSerializer $serializer Serializer to encode/decode Message objects.
     * @param RequestIdGenerator $idGen Generates unique IDs for requests.
     * @param float $timeout Timeout (in seconds) for request() before failure. Defaults to 0.5 because stdio should be very fast.
     */
    public function __construct(
        private readonly MessageSerializer $defaultSerializer = new NativeMessageSerializer(),
        private readonly RequestIdGenerator $idGen = new PidHrtimeRequestIdGenerator(),
        private readonly float $timeout = self::DEFAULT_TIMEOUT,
    ) {
    }

    /**
     * Instantiate a new IpcSession on the provided streams.
     *
     * @return IpcSession A new session you can notify(), request(), etc.
     */
    public function createSession(MessageTransport $transport): IpcSession
    {
        $session = new IpcSession(
            $transport,
            $this->idGen,
            $this->timeout
        );
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
        return $this->createSession(new FramedStreamMessageTransport(
            $write,
            [$read, ...($read2 !== null ? [$read2] : [])],
            $this->defaultSerializer
        ));
    }

    /**
     * Bind a session to the current process's STDIN/STDOUT.
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
