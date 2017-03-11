<?php

namespace Amp\Test;

use Amp\Pause;
use Amp\Loop;

class PauseTest extends \PHPUnit\Framework\TestCase {
    public function testPause() {
        $time = 100;
        $value = "test";
        $start = microtime(true);

        Loop::run(function () use (&$result, $time, $value) {
            $promise = new Pause($time, $value);

            $callback = function ($exception, $value) use (&$result) {
                $result = $value;
            };

            $promise->when($callback);
        });

        $this->assertGreaterThanOrEqual($time, (microtime(true) - $start) * 1000);
        $this->assertSame($value, $result);
    }

    public function testUnreference() {
        $time = 100;
        $value = "test";
        $start = microtime(true);

        $invoked = false;
        Loop::run(function () use (&$invoked, $time, $value) {
            $promise = new Pause($time, $value);
            $promise->unreference();

            $callback = function ($exception, $value) use (&$invoked) {
                $invoked = true;
            };

            $promise->when($callback);
        });

        $this->assertLessThanOrEqual($time, (microtime(true) - $start) * 1000);
        $this->assertFalse($invoked);
    }

    /**
     * @depends testUnreference
     */
    public function testReference() {
        $time = 100;
        $value = "test";
        $start = microtime(true);

        $invoked = false;
        Loop::run(function () use (&$invoked, $time, $value) {
            $promise = new Pause($time, $value);
            $promise->unreference();
            $promise->reference();

            $callback = function ($exception, $value) use (&$invoked) {
                $invoked = true;
            };

            $promise->when($callback);
        });

        $this->assertGreaterThanOrEqual($time, (microtime(true) - $start) * 1000);
        $this->assertTrue($invoked);
    }
}
