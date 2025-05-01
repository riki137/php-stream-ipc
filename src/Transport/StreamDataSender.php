<?php

declare(strict_types=1);

namespace PhpStreamIpc\Transport;

use Amp\ByteStream\WritableResourceStream;

final readonly class StreamDataSender implements DataSender
{
    public function __construct(private WritableResourceStream $output)
    {
    }

    public function send(string $message): void
    {
        $this->output->write($message . PHP_EOL);
    }
}
