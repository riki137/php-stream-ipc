<?php

declare(strict_types=1);

namespace PhpStreamIpc\Envelope;

use PhpStreamIpc\Message\Message;

/**
 * Represents a response message wrapped with the identifier of the original request.
 * This envelope is used to transport a {@see Message} that is a reply to a previous request,
 * identified by the `id`.
 */
final readonly class ResponseEnvelope implements Message
{
    /**
     * Constructs a new ResponseEnvelope.
     *
     * @param string $id The unique identifier of the original {@see RequestEnvelope} this response corresponds to.
     * @param Message $response The actual {@see Message} payload of the response.
     */
    public function __construct(public string $id, public Message $response)
    {
    }
}
