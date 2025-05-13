<?php

declare(strict_types=1);

namespace PhpStreamIpc\Envelope\Id;

/**
 * Generates unique string identifiers for correlating IPC requests and responses.
 */
interface RequestIdGenerator
{
    /**
     * Generate a new unique identifier for a request.
     *
     * @return string The generated request identifier.
     */
    public function generate(): string;
}
