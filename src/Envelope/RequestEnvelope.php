<?php

declare(strict_types=1);

namespace PhpStreamIpc\Envelope;

use PhpStreamIpc\Message\Message;

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
     * @param string $id The unique identifier for this request. This ID will be used to match the response.
     * @param Message $request The actual {@see Message} payload of the request.
     */
    public function __construct(public string $id, public Message $request)
    {
    }
}
