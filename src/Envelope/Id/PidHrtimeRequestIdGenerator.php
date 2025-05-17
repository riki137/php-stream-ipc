<?php

declare(strict_types=1);

namespace PhpStreamIpc\Envelope\Id;

/**
 * Generates unique request IDs based on the current process ID (PID) and a static incrementing counter.
 * This generator aims to create IDs that are unique across different processes and impossible to collide
 * even with rapid generation within the same process.
 */
final class PidHrtimeRequestIdGenerator implements RequestIdGenerator
{
    private static ?string $pid = null;
    private static int $counter = 0;

    /**
     * Generates a new unique request ID.
     * The ID is a string composed of the PID and an incrementing counter.
     * Example format: "<pid>.<counter>"
     *
     * @return string A unique request identifier.
     */
    public function generate(): string
    {
        if (self::$pid === null) {
            $pid = getmypid();
            if ($pid === false) {
                // fallback to random if PID cannot be determined
                self::$pid = uniqid('', true);
            } else {
                self::$pid = (string)$pid;
            }
        }

        $count = ++self::$counter;

        return sprintf('%s.%d', self::$pid, $count);
    }
}
