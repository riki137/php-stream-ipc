<?php

declare(strict_types=1);

namespace PhpStreamIpc\Envelope;

use PhpStreamIpc\Message\Message;

/**
 * Wraps a request Message with a unique identifier for correlating responses.
 */
final readonly class RequestEnvelope implements Message
{
    /**
     * RequestEnvelope constructor.
     *
     * @param string $id The unique identifier for the request.
     * @param Message $request The original request message payload.
     */
    public function __construct(public string $id, public Message $request)
    {
    }
}
