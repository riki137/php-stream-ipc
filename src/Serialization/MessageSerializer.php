<?php

declare(strict_types=1);

namespace PhpStreamIpc\Serialization;

use PhpStreamIpc\Message\Message;

interface MessageSerializer
{
    public function serialize(Message $data): string;

    public function deserialize(string $data): Message;
}
