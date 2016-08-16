<?php

declare(strict_types=1);

namespace Amp\Test;

use Amp;
use Amp\Pause;
use Interop\Async\Loop;

class PauseTest extends \PHPUnit_Framework_TestCase {
    public function testPause() {
        $time = 100;
        $value = "test";
        $start = microtime(true);

        Loop::execute(function () use (&$result, $time, $value) {
            $awaitable = new Pause($time, $value);

            $callback = function ($exception, $value) use (&$result) {
                $result = $value;
            };

            $awaitable->when($callback);
        });

        $this->assertLessThanOrEqual($time, microtime(true) - $start);
        $this->assertSame($value, $result);
    }
}
