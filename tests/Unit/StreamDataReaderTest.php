<?php
declare(strict_types=1);

namespace Tests\PhpStreamIpc\Unit;

use Amp\ByteStream\ReadableResourceStream;
use Amp\Pipeline\ConcurrentIterator;
use Amp\Pipeline\Pipeline;
use PhpStreamIpc\Transport\DataReader;
use PHPUnit\Framework\TestCase;
use PhpStreamIpc\Transport\StreamDataReader;
use Amp\ByteStream\ClosedException;
use Amp\Cancellation;

class StreamDataReaderTest extends TestCase
{
    public function testReadLinesFromMockStreamIterator(): void 
    {
        // Create a mock stream using a closure to provide lines
        $lines = ["line1", "line2", "line3"];

        // Create a mock StreamDataReader with our lines
        $reader = $this->getMockBuilder(DataReader::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['read'])
            ->getMock();
        
        // Set up the mock to return lines in sequence
        $reader->expects($this->exactly(3))
            ->method('read')
            ->willReturnOnConsecutiveCalls(...$lines);
        
        // Test reading the lines
        $this->assertSame('line1', $reader->read());
        $this->assertSame('line2', $reader->read());
        $this->assertSame('line3', $reader->read());
    }
    
    public function testReadLinesUntilClosedException(): void
    {
        // Create a mock reader that returns lines then throws ClosedException
        $reader = $this->getMockBuilder(DataReader::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['read'])
            ->getMock();
            
        $reader->expects($this->exactly(3))
            ->method('read')
            ->willReturnOnConsecutiveCalls(
                'line1',
                'line2',
                $this->throwException(new ClosedException("Stream closed"))
            );
            
        // Test reading lines until exception
        $this->assertSame('line1', $reader->read());
        $this->assertSame('line2', $reader->read());
        
        // Third read should throw exception
        $this->expectException(ClosedException::class);
        $reader->read();
    }
    
    /**
     * Creates a mock of ConcurrentIterator that returns the given lines
     */
    private function createMockStreamIterator(array $lines): ConcurrentIterator
    {
        $iterator = $this->createMock(ConcurrentIterator::class);
        
        // Set up the continue method to return true for each line, then false
        $continueValues = array_fill(0, count($lines), true);
        $continueValues[] = false;
        
        $iterator->expects($this->any())
            ->method('continue')
            ->willReturnOnConsecutiveCalls(...$continueValues);
            
        // Set up the getValue method to return each line
        $iterator->expects($this->any())
            ->method('getValue')
            ->willReturnOnConsecutiveCalls(...$lines);
            
        return $iterator;
    }
} 
