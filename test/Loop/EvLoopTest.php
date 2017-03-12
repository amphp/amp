<?php

namespace Amp\Test\Loop;

use Amp\Loop\EvDriver;

/**
 * @requires extension ev
 */
class EvDriverTest extends DriverTest {
    public function getFactory(): callable {
        return function () {
            return new EvDriver;
        };
    }
}
