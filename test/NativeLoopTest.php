<?php

namespace Amp\Test\Loop;

use Amp\Loop\NativeLoop;
use Amp\Test\LoopTest;

class NativeLoopLoopTest extends LoopTest {
    public function getFactory() {
        return function () {
            return new NativeLoop;
        };
    }
}
