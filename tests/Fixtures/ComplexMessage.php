<?php
namespace PhpStreamIpc\Tests\Fixtures;

use PhpStreamIpc\Message\Message;

final class ComplexMessage implements Message
{
    private string $secret;

    public function __construct(
        public SimpleMessage $inner,
        string $secret,
        public array $list = []
    ) {
        $this->secret = $secret;
    }

    public function getSecret(): string
    {
        return $this->secret;
    }
}
