<?php
declare(strict_types=1);

namespace Tests\PhpStreamIpc\Unit;

use PHPUnit\Framework\TestCase;
use PhpStreamIpc\Envelope\Id\PidHrtimeRequestIdGenerator;

final class PidHrtimeRequestIdGeneratorTest extends TestCase
{
    public function testGenerateReturnsPidDotTimestamp(): void
    {
        $gen = new PidHrtimeRequestIdGenerator();
        $id = $gen->generate();

        $this->assertMatchesRegularExpression('/^\d+\.\d+$/', $id);
        [$pid, $time] = explode('.', $id);
        $this->assertSame((string)getmypid(), $pid);
        $this->assertIsNumeric($time);
    }

    public function testRepeatedGenerateUpdatesTimestampButKeepsPid(): void
    {
        $gen = new PidHrtimeRequestIdGenerator();
        $id1 = $gen->generate();
        usleep(1);
        $id2 = $gen->generate();

        $this->assertNotSame($id1, $id2);
        $pid1 = explode('.', $id1)[0];
        $pid2 = explode('.', $id2)[0];
        $this->assertSame($pid1, $pid2);
    }
} 
