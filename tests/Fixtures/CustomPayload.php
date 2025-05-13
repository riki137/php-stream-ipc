<?php

declare(strict_types=1);

namespace Tests\PhpStreamIpc\Fixtures;

use PhpStreamIpc\Message\Message;

final class CustomPayload implements Message
{
    public array $items;
}
