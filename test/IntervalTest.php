<?php declare(strict_types=1);

namespace Amp;

class IntervalTest extends TestCase
{
    public function testCancelWhenDestroyed(): void
    {
        $timeout = 0.01;
        $invocationCount = 0;
        $interval = new Interval($timeout, function () use (&$invocationCount): void {
            ++$invocationCount;
        });

        delay($timeout * 1.5);

        self::assertGreaterThan(0, $invocationCount);
        $originalCount = $invocationCount;

        unset($interval);

        delay($timeout * 10);

        self::assertSame($originalCount, $invocationCount);
    }

    public function testEnableAndDisable(): void
    {
        $timeout = 0.01;
        $invocationCount = 0;
        $interval = new Interval($timeout, function () use (&$invocationCount): void {
            ++$invocationCount;
        });

        $interval->disable();
        self::assertFalse($interval->isEnabled());

        delay($timeout * 2);

        self::assertSame(0, $invocationCount);

        $interval->enable();
        self::assertTrue($interval->isEnabled());

        delay($timeout * 1.5);

        self::assertSame(1, $invocationCount);
    }
}
