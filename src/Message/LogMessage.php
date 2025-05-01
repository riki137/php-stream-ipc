<?php

declare(strict_types=1);

namespace PhpStreamIpc\Message;

use PhpStreamIpc\Message\Message;

final readonly class LogMessage implements Message
{
    public function __construct(public string $message, public string $level = 'info')
    {
    }
}
