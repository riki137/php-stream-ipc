<?php
namespace PhpStreamIpc\Tests\Fixtures;

use PhpStreamIpc\Message\Message;

final readonly class SimpleMessage implements Message
{
    public function __construct(public string $text)
    {
    }
}
