<?php

declare(strict_types=1);

namespace PhpStreamIpc\Transport;

interface DataSender
{
    public function send(string $message): void;
}
