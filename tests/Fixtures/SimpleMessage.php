<?php
namespace StreamIpc\Tests\Fixtures;

use StreamIpc\Message\Message;

final readonly class SimpleMessage implements Message
{
    public function __construct(public string $text)
    {
    }
}
