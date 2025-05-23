<?php

declare(strict_types=1);

namespace PhpStreamIpc\Envelope\Id;

/**
 * Defines the contract for generating unique string identifiers.
 * These identifiers are used to correlate IPC requests with their corresponding responses.
 */
interface RequestIdGenerator
{
    /**
     * Generates a new, unique string identifier.
     *
     * @return string A unique identifier, typically for an IPC request.
     */
    public function generate(): string;
}
