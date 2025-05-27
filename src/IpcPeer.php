<?php
declare(strict_types=1);

namespace PhpStreamIpc;

use PhpStreamIpc\Envelope\Id\PidCounterRequestIdGenerator;
use PhpStreamIpc\Envelope\Id\RequestIdGenerator;
use PhpStreamIpc\Serialization\MessageSerializer;
use PhpStreamIpc\Serialization\NativeMessageSerializer;
use PhpStreamIpc\Transport\MessageTransport;

/**
 * Base class for IpcPeer variants bound to a specific transport implementation.
 */
abstract class IpcPeer
{
    /** @var IpcSession[] */
    protected array $sessions = [];
    protected MessageSerializer $defaultSerializer;
    protected RequestIdGenerator $idGen;

    public function __construct(?MessageSerializer $defaultSerializer = null, ?RequestIdGenerator $idGen = null)
    {
        $this->defaultSerializer = $defaultSerializer ?? new NativeMessageSerializer();
        $this->idGen = $idGen ?? new PidCounterRequestIdGenerator();
    }

    /**
     * @param MessageTransport $transport
     */
    protected function createSession(MessageTransport $transport): IpcSession
    {
        $session = new IpcSession($this, $transport, $this->idGen);
        $this->sessions[] = $session;
        return $session;
    }

    public function removeSession(IpcSession $session): void
    {
        $this->sessions = array_filter($this->sessions, fn($s) => $s !== $session);
    }

    abstract public function tick(?float $timeout = null): void;

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
