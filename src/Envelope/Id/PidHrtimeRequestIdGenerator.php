<?php

declare(strict_types=1);

namespace PhpStreamIpc\Envelope\Id;

use PhpStreamIpc\Envelope\Id\RequestIdGenerator;

final class PidHrtimeRequestIdGenerator implements RequestIdGenerator
{
    private static ?string $pid = null;

    public function generate(): string
    {
        self::$pid ??= (string)getmypid();
        return self::$pid . '.' . hrtime(true);
    }
}
