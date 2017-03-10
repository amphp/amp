<?php

namespace Amp\Test;

use Amp\Loop;
use PHPUnit_Framework_Test;

class LoopReset extends \PHPUnit_Framework_BaseTestListener {
    public function endTest(PHPUnit_Framework_Test $test, $time) {
        Loop::set((new Loop\Factory)->create());
    }
}