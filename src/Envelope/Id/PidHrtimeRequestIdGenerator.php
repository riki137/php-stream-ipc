<?php

declare(strict_types=1);

namespace PhpStreamIpc\Envelope\Id;

final class PidHrtimeRequestIdGenerator implements RequestIdGenerator
{
    private static ?string $pid = null;
    private static int $counter = 0;

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

        // nanoseconds since arbitrary point
        $ns = hrtime(true);

        // increment counter to avoid collisions within same nanosecond
        $count = ++self::$counter;

        return sprintf('%s.%d.%d', self::$pid, $ns, $count);
    }
}
