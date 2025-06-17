<?php

declare(strict_types=1);

namespace StreamIpc\Envelope;

use StreamIpc\Message\Message;

/**
 * Represents a request message wrapped with a unique identifier.
 * This envelope is used to transport a {@see Message} that expects a response,
 * allowing the response to be correlated back to the original request using the ID.
 */
final readonly class RequestEnvelope implements Message
{
    /**
     * Create a request envelope.
     *
     * @param string  $id      Unique identifier for this request used to correlate the response.
     * @param Message $request The request message payload.
     */
    public function __construct(public string $id, public Message $request)
    {
    }
}
