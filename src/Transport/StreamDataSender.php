<?php

declare(strict_types=1);

namespace PhpStreamIpc\Transport;

use Amp\ByteStream\WritableResourceStream;

/**
 * Writes serialized message payloads to a WritableResourceStream, appending a newline delimiter for framing.
 */
final readonly class StreamDataSender implements DataSender
{
    /**
     * StreamDataSender constructor.
     *
     * @param WritableResourceStream $output The output stream to write message data.
     */
    public function __construct(private WritableResourceStream $output)
    {
    }

    /**
     * Write the message followed by a newline to the underlying stream.
     *
     * @param string $message The serialized message to send.
     * @return void
     */
    public function send(string $message): void
    {
        $this->output->write($message . PHP_EOL);
    }
}
