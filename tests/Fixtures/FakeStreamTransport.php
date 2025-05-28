<?php
namespace StreamIpc\Tests\Fixtures;

use StreamIpc\Transport\MessageTransport;
use StreamIpc\Message\Message;
use StreamIpc\IpcSession;

final class FakeStreamTransport implements MessageTransport
{
    /** @var float[] */
    public array $tickArgs = [];
    public array $streams = [];


    public function __construct()
    {
    }

    public function send(Message $message): void
    {
        // ignore
    }

    public function getReadStreams(): array
    {
        $this->tickArgs[] = 0.0;
        return $this->streams;
    }

    public function readFromStream($stream): array
    {
        return [];
    }
}
