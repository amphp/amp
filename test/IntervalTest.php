<?php

namespace Amp;

use Amp\PHPUnit\AsyncTestCase;

class IntervalTest extends AsyncTestCase
{
    public function testCancelOnDestruction(): void
    {
        $this->setTimeout(0.1);

        $count = 0;
        $object = new class($count) {
            private Interval $interval;

            public function __construct(int &$count)
            {
                $this->interval = new Interval(0.01, static function (Interval $cancel) use (&$count): void {
                    ++$count;
                });
            }
        };

        delay(0.039);
        unset($object); // Should destroy PeriodicFunction object and cancel loop watcher.
        self::assertSame(3, $count);

        delay(0.029);
        self::assertSame(3, $count);
    }

    public function testCancelCallable(): void
    {
        $this->setTimeout(0.1);

        $count = 0;
        $interval = new Interval(0.01, static function (Interval $cancel) use (&$count): void {
            $cancel->disable();
            ++$count;
        });

        delay(0.039);
        self::assertSame(1, $count);
        self::assertFalse($interval->isEnabled());

        delay(0.029);
        self::assertSame(1, $count);
    }

    public function testDisableAndEnable(): void
    {
        $this->setTimeout(0.1);

        $count = 0;
        $interval = new Interval(0.01, static function (Interval $cancel) use (&$count): void {
            ++$count;
        });

        delay(0.029);
        self::assertSame(2, $count);
        $interval->disable();
        self::assertFalse($interval->isEnabled());

        delay(0.029);
        self::assertSame(2, $count);
        $interval->enable();
        self::assertTrue($interval->isEnabled());

        delay(0.029);
        self::assertSame(4, $count);
    }
}
