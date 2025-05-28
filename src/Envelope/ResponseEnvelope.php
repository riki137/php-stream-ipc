<?php

declare(strict_types=1);

namespace StreamIpc\Envelope;

use StreamIpc\Message\Message;

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
     * @param $id       string Identifier of the original request this response corresponds to.
     * @param $response Message payload of the response.
     */
    public function __construct(public string $id, public Message $response)
    {
    }
}
