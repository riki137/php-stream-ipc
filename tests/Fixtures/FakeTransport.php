<?php
namespace PhpStreamIpc\Tests\Fixtures;

use PhpStreamIpc\Transport\MessageTransport;
use PhpStreamIpc\Message\Message;
use PhpStreamIpc\IpcSession;

final class FakeTransport implements MessageTransport
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
