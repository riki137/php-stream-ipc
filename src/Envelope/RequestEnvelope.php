<?php

declare(strict_types=1);

namespace PhpStreamIpc\Envelope;

use PhpStreamIpc\Message\Message;

final readonly class RequestEnvelope implements Message
{
    public function __construct(public string $id, public Message $request)
    {
    }
}
