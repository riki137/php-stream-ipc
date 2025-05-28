<?php

declare(strict_types=1);

namespace StreamIpc\Envelope\Id;

/**
 * Defines the contract for generating unique string identifiers.
 * These identifiers are used to correlate IPC requests with their corresponding responses.
 */
interface RequestIdGenerator
{
    /**
     * Generates a new, unique string identifier.
     */
    public function generate(): string;
}
