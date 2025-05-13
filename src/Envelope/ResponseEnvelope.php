<?php

declare(strict_types=1);

namespace PhpStreamIpc\Envelope;

use PhpStreamIpc\Message\Message;

/**
 * Wraps a response Message with its request identifier to complete the request–response cycle.
 */
final readonly class ResponseEnvelope implements Message
{
    /**
     * ResponseEnvelope constructor.
     *
     * @param string $id The identifier of the original request.
     * @param Message $response The response message payload.
     */
    public function __construct(public string $id, public Message $response)
    {
    }
}
