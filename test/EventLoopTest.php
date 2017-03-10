<?php

namespace Amp\Test\Loop;

use Amp\Loop\EventLoop;
use Amp\Test\LoopTest;

/**
 * @requires extension event
 */
class EventLoopLoopTest extends LoopTest {
    public function getFactory(): callable {
        return function () {
            return new EventLoop;
        };
    }
}
