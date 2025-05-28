<?php
declare(strict_types=1);

namespace StreamIpc;

use StreamIpc\Envelope\Id\PidCounterRequestIdGenerator;
use StreamIpc\Envelope\Id\RequestIdGenerator;
use StreamIpc\Serialization\MessageSerializer;
use StreamIpc\Serialization\NativeMessageSerializer;
use StreamIpc\Transport\MessageTransport;

/**
 * Base class for IpcPeer variants bound to a specific transport implementation.
 */
abstract class IpcPeer
{
    /** @var IpcSession[] */
    protected array $sessions = [];

    protected MessageSerializer $defaultSerializer;

    protected RequestIdGenerator $idGen;

    /**
     * Initialise the peer with optional serializer and ID generator.
     * Defaults are used when arguments are not supplied.
     */
    public function __construct(?MessageSerializer $defaultSerializer = null, ?RequestIdGenerator $idGen = null)
    {
        $this->defaultSerializer = $defaultSerializer ?? new NativeMessageSerializer();
        $this->idGen = $idGen ?? new PidCounterRequestIdGenerator();
    }

    /**
     * Wrap the given transport in an {@see IpcSession} and track it.
     */
    protected function createSession(MessageTransport $transport): IpcSession
    {
        $session = new IpcSession($this, $transport, $this->idGen);
        $this->sessions[] = $session;
        return $session;
    }

    /**
     * Remove a session previously created by this peer.
     */
    public function removeSession(IpcSession $session): void
    {
        $this->sessions = array_filter($this->sessions, fn($s) => $s !== $session);
    }

    abstract public function tick(?float $timeout = null): void;

    /**
     * Repeatedly calls {@see tick()} for the specified duration.
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
