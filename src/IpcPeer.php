<?php
declare(strict_types=1);

namespace PhpStreamIpc;

use PhpStreamIpc\Envelope\Id\PidHrtimeRequestIdGenerator;
use PhpStreamIpc\Envelope\Id\RequestIdGenerator;
use PhpStreamIpc\Serialization\MessageSerializer;
use PhpStreamIpc\Serialization\NativeMessageSerializer;
use PhpStreamIpc\Transport\FramedStreamMessageTransport;
use PhpStreamIpc\Transport\MessageTransport;
use PhpStreamIpc\Transport\StreamClosedException;

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

    public function __construct(
        ?MessageSerializer $defaultSerializer = null,
        ?RequestIdGenerator $idGen = null
    ) {
        $this->defaultSerializer = $defaultSerializer ?? new NativeMessageSerializer();
        $this->idGen = $idGen ?? new PidHrtimeRequestIdGenerator();
    }

    public function createSession(MessageTransport $transport): IpcSession
    {
        $session = new IpcSession($this, $transport, $this->idGen);
        $this->sessions[] = $session;
        return $session;
    }

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

    public function createStdioSession(): IpcSession
    {
        return $this->createStreamSession(STDOUT, STDIN);
    }

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
