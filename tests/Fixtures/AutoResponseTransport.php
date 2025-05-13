<?php

declare(strict_types=1);

namespace Tests\PhpStreamIpc\Fixtures;

use PhpStreamIpc\Transport\MessageTransport;
use PhpStreamIpc\Message\Message;
use PhpStreamIpc\Envelope\RequestEnvelope;
use PhpStreamIpc\Envelope\ResponseEnvelope;
use PhpStreamIpc\Message\LogMessage;
use Amp\Cancellation;

final class AutoResponseTransport implements MessageTransport
{
    private ?ResponseEnvelope $next = null;

    public function send($message): void
    {
        if ($message instanceof RequestEnvelope) {
            $this->next = new ResponseEnvelope(
                $message->id,
                new LogMessage('pong', 'info')
            );
        }
    }

    public function read(?Cancellation $cancellation = null): Message
    {
        if ($this->next === null) {
            throw new \RuntimeException('Nothing to read');
        }
        $out = $this->next;
        $this->next = null;
        return $out;
    }
}
