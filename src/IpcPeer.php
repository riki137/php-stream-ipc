<?php
declare(strict_types=1);

namespace PhpStreamIpc;

use PhpStreamIpc\Envelope\Id\PidHrtimeRequestIdGenerator;
use PhpStreamIpc\Envelope\Id\RequestIdGenerator;
use PhpStreamIpc\Serialization\MessageSerializer;
use PhpStreamIpc\Serialization\NativeMessageSerializer;
use PhpStreamIpc\Transport\FramedStreamMessageTransport;
use PhpStreamIpc\Transport\MessageTransport;

/**
 * Manages IPC sessions for communication over stdio, pipes, or child processes.
 * Facilitates creating sessions, broadcasting notifications, and configuring serialization and request ID generation.
 */
final class IpcPeer
{
    /** @var IpcSession[] Active sessions created by this peer */
    private array $sessions = [];

    private MessageSerializer $defaultSerializer;

    private RequestIdGenerator $idGen;

    /**
     * Constructs a new IpcPeer.
     *
     * @param MessageSerializer|null $defaultSerializer Optional custom message serializer. Defaults to NativeMessageSerializer.
     * @param RequestIdGenerator|null $idGen Optional custom request ID generator. Defaults to PidHrtimeRequestIdGenerator.
     */
    public function __construct(
        ?MessageSerializer $defaultSerializer = null,
        ?RequestIdGenerator $idGen = null
    ) {
        $this->defaultSerializer = $defaultSerializer ?? new NativeMessageSerializer();
        $this->idGen = $idGen ?? new PidHrtimeRequestIdGenerator();
    }

    /**
     * Creates a new IPC session with the given message transport.
     *
     * @param MessageTransport $transport The transport to use for the session.
     * @return IpcSession The newly created IPC session.
     */
    public function createSession(MessageTransport $transport): IpcSession
    {
        $session = new IpcSession($this, $transport, $this->idGen);
        $this->sessions[] = $session;
        return $session;
    }

    /**
     * Creates a new IPC session using the provided stream resources.
     *
     * @param resource $write The stream resource for writing messages.
     * @param resource $read The primary stream resource for reading messages.
     * @param resource|null $read2 An optional, additional stream resource for reading messages.
     * @return IpcSession The created IPC session.
     */
    public function createStreamSession($write, $read, $read2 = null): IpcSession
    {
        $reads = [$read];
        if ($read2 !== null) {
            $reads[] = $read2;
        }
        return $this->createSession(
            new FramedStreamMessageTransport(
                $write,
                $reads,
                $this->defaultSerializer
            )
        );
    }

    /**
     * Creates a new IPC session that communicates over standard input (STDIN) and standard output (STDOUT).
     *
     * @return IpcSession The created IPC session using stdio.
     */
    public function createStdioSession(): IpcSession
    {
        return $this->createStreamSession(STDOUT, STDIN);
    }

    /**
     * Removes an IPC session from this peer.
     * This effectively stops the peer from managing or ticking the session.
     *
     * @param IpcSession $session The session to remove.
     */
    public function removeSession(IpcSession $session): void
    {
        $this->sessions = array_filter($this->sessions, fn($s) => $s !== $session);
    }

    /**
     * Perform a single stream_select over all session streams, then dispatch.
     *
     * @param float|null $timeout in seconds (null = block indefinitely)
     */
    public function tick(?float $timeout = null): void
    {
        foreach ($this->sessions as $s) {
            $s->getTransport()->tick($this->sessions, $timeout);
            return;
        }
    }

    /**
     * Runs the tick loop for a specified duration.
     * This method will call `tick()` repeatedly until the given number of seconds has elapsed.
     *
     * @param float $seconds The duration in seconds to run the tick loop.
     */
    public function tickFor(float $seconds): void
    {
        $start = microtime(true);
        while ($seconds > 0) {
            $this->tick($seconds);
            $seconds -= microtime(true) - $start;
            $start = microtime(true);
        }
    }
}
