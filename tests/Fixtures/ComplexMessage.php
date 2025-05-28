<?php
namespace StreamIpc\Tests\Fixtures;

use StreamIpc\Message\Message;

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
