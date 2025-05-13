<?php declare(strict_types=1);

namespace PhpStreamIpc\Transport;

use Amp\ByteStream\ClosedException;
use Amp\ByteStream\ReadableResourceStream;
use Amp\Cancellation;
use Amp\Pipeline\ConcurrentIterator;
use Amp\Pipeline\Pipeline;
use InvalidArgumentException;
use function count;

/**
 * Reads line-delimited messages from one or more ReadableResourceStreams,
 * merging multiple streams into a single pipeline for consumption.
 */
final class StreamDataReader implements DataReader
{
    private ConcurrentIterator $iterator;

    public function __construct(ReadableResourceStream ...$streams)
    {
        $pipelines = array_map(
            static fn(ReadableResourceStream $stream) => self::generateLines($stream),
            $streams
        );
        $pipeline = match (count($pipelines)) {
            0 => throw new InvalidArgumentException('At least one stream must be provided'),
            1 => Pipeline::fromIterable(reset($pipelines)),
            default => Pipeline::merge($pipelines),
        };
        $this->iterator = $pipeline->getIterator();
    }

    /**
     * @param ReadableResourceStream $stream
     * @return iterable<string>
     */
    private static function generateLines(ReadableResourceStream $stream): iterable
    {
        $partial = '';
        foreach ($stream as $chunk) {
            $partial .= $chunk;
            while (false !== ($pos = strpos($partial, "\n"))) {
                yield substr($partial, 0, $pos);
                $partial = substr($partial, $pos + 1);
            }
        }
        if ($partial !== '') {
            yield $partial;
        }
    }

    /**
     * Read the next message line from the underlying streams.
     *
     * @param Cancellation|null $cancellation Optional cancellation token to abort the read.
     * @return string The raw serialized message payload.
     * @throws ClosedException When the stream is closed and no more data is available.
     */
    public function read(?Cancellation $cancellation = null): string
    {
        if ($this->iterator->continue($cancellation)) {
            return $this->iterator->getValue();
        }

        throw new ClosedException('Stream closed');
    }
}
