<?php

namespace Amp\Test;

use Amp\Loop;
use PHPUnit\Framework\TestCase;

class LoopTest extends TestCase {
    public function testDelayWithNegativeDelay() {
        $this->expectException(\InvalidArgumentException::class);

        Loop::delay(-1, function () {});
    }

    public function testRepeatWithNegativeInterval() {
        $this->expectException(\InvalidArgumentException::class);

        Loop::repeat(-1, function () {});
    }
}