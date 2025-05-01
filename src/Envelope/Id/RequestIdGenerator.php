<?php

declare(strict_types=1);

namespace PhpStreamIpc\Envelope\Id;

interface RequestIdGenerator
{
    /**
     * @return string Unique ID for a new request
     */
    public function generate(): string;
}
