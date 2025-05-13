<?php

declare(strict_types=1);

namespace PhpStreamIpc\Transport;

use Amp\Cancellation;

/**
 * Defines a contract for reading serialized message payloads from a transport, such as a stream or socket.
 */
interface DataReader
{
    /**
     * Read the next data frame as a string.
     *
     * @param Cancellation|null $cancellation Optional cancellation token for the read operation.
     * @return string The raw serialized message payload.
     */
    public function read(?Cancellation $cancellation = null): string;
}
