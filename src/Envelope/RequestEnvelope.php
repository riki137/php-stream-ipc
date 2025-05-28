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
     * Constructs a new RequestEnvelope.
     *
     * @param $id      string Unique identifier for this request, used to match the response.
     * @param $request Message The message payload of the request.
     */
    public function __construct(public string $id, public Message $request)
    {
    }
}
