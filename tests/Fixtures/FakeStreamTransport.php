<?php
namespace PhpStreamIpc\Tests\Fixtures;

use PhpStreamIpc\Transport\MessageTransport;
use PhpStreamIpc\Message\Message;
use PhpStreamIpc\IpcSession;

final class FakeStreamTransport implements MessageTransport
{
    /** @var float[] */
    public array $tickArgs = [];
    /** @var IpcSession[][] */
    public array $sessions = [];


    public function __construct()
    {
    }

    public function send(Message $message): void
    {
        // ignore
    }

    public function tick(array $sessions, ?float $timeout = null): void
    {
        $this->tickArgs[] = $timeout;
        $this->sessions[] = $sessions;
    }
}
