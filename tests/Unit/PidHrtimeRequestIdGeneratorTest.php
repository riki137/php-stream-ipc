<?php
namespace StreamIpc\Tests\Unit;

use StreamIpc\Envelope\Id\PidCounterRequestIdGenerator;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class PidHrtimeRequestIdGeneratorTest extends TestCase
{
    private function resetGenerator(): void
    {
        $pidProp = new ReflectionProperty(PidCounterRequestIdGenerator::class, 'pid');
        $pidProp->setAccessible(true);
        $pidProp->setValue(null, null);
        $counterProp = new ReflectionProperty(PidCounterRequestIdGenerator::class, 'counter');
        $counterProp->setAccessible(true);
        $counterProp->setValue(null, 0);
    }

    public function testGeneratesIncreasingIds(): void
    {
        $this->resetGenerator();
        $gen = new PidCounterRequestIdGenerator();
        $id1 = $gen->generate();
        $id2 = $gen->generate();

        $this->assertMatchesRegularExpression('/^\d+\.\d+$/', $id1);
        $this->assertSame(explode('.', $id1)[0], explode('.', $id2)[0]);
        $this->assertSame((int)explode('.', $id1)[1] + 1, (int)explode('.', $id2)[1]);
    }
}
