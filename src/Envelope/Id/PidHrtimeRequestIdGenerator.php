<?php

declare(strict_types=1);

namespace PhpStreamIpc\Envelope\Id;

use PhpStreamIpc\Envelope\Id\RequestIdGenerator;

/**
 * Concatenates the current process ID with hrtime to produce unique request identifiers.
 */
final class PidHrtimeRequestIdGenerator implements RequestIdGenerator
{
    private static ?string $pid = null;

    /**
     * Create a new unique request ID using the current process ID and hrtime.
     *
     * @return string The generated request identifier.
     */
    public function generate(): string
    {
        self::$pid ??= (string)getmypid();
        return self::$pid . '.' . hrtime(true);
    }
}
