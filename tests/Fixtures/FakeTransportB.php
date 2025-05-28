<?php
namespace StreamIpc\Tests\Fixtures;

use StreamIpc\Transport\MessageTransport;
use StreamIpc\Message\Message;
use StreamIpc\IpcSession;

final class FakeTransportB implements MessageTransport
{
    /** @var Message[] */
    public array $sent = [];
    public array $readCalls = [];

    public function send(Message $message): void
    {
        $this->sent[] = $message;
    }

    public function getReadStreams(): array
    {
        return [];
    }

    public function readFromStream($stream): array
    {
        $this->readCalls[] = $stream;
        return [];
    }
}
