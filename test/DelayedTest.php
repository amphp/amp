<?php

namespace Amp\Test;

use Amp\Delayed;
use Amp\Loop;
use function Amp\Promise\wait;

class DelayedTest extends BaseTest
{
    public function testDelayed()
    {
        $time = 100;
        $value = "test";
        $start = \microtime(true);

        Loop::run(static function () use (&$result, $time, $value) {
            $promise = new Delayed($time, $value);

            $callback = static function ($exception, $value) use (&$result) {
                $result = $value;
            };

            $promise->onResolve($callback);
        });

        $this->assertGreaterThanOrEqual($time - 1 /* 1ms grace period */, (\microtime(true) - $start) * 1000);
        $this->assertSame($value, $result);
    }

    public function testUnreference()
    {
        $time = 100;
        $value = "test";
        $start = \microtime(true);

        $invoked = false;
        Loop::run(static function () use (&$invoked, $time, $value, &$promise) {
            $promise = new Delayed($time, $value);
            $promise->unreference();

            $callback = static function () use (&$invoked) {
                $invoked = true;
            };

            $promise->onResolve($callback);
        });

        $this->assertLessThanOrEqual($time - 1 /* 1ms grace period */, (\microtime(true) - $start) * 1000);
        $this->assertFalse($invoked);

        // clear watcher
        $promise->reference();
        wait($promise);
    }

    /**
     * @depends testUnreference
     */
    public function testReference()
    {
        $time = 100;
        $value = "test";
        $start = \microtime(true);

        $invoked = false;
        Loop::run(static function () use (&$invoked, $time, $value) {
            $promise = new Delayed($time, $value);
            $promise->unreference();
            $promise->reference();

            $callback = static function ($exception, $value) use (&$invoked) {
                $invoked = true;
            };

            $promise->onResolve($callback);
        });

        $this->assertGreaterThanOrEqual($time - 1 /* 1ms grace period */, (\microtime(true) - $start) * 1000);
        $this->assertTrue($invoked);
    }
}
