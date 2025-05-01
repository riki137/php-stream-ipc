<?php declare(strict_types=1);

namespace PhpStreamIpc\Transport;

use Amp\ByteStream\ClosedException;
use Amp\ByteStream\ReadableResourceStream;
use Amp\Cancellation;
use Amp\Pipeline\ConcurrentIterator;
use Amp\Pipeline\Pipeline;
use InvalidArgumentException;
use function count;

final class StreamDataReader implements DataReader
{
    private ConcurrentIterator $iterator;

    public function __construct(ReadableResourceStream ...$streams)
    {
        $pipelines = array_map(
            static fn(ReadableResourceStream $stream) => Pipeline::fromIterable(self::generateLines($stream)),
            $streams
        );
        $pipeline = match (count($pipelines)) {
            0 => throw new InvalidArgumentException('At least one stream must be provided'),
            1 => reset($pipelines),
            default => Pipeline::merge(...$pipelines),
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
     * @throws ClosedException
     */
    public function read(?Cancellation $cancellation = null): string
    {
        if ($this->iterator->continue($cancellation)) {
            return $this->iterator->getValue();
        }

        throw new ClosedException('Stream closed');
    }
}
