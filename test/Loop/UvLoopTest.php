<?php

namespace Amp\Test\Loop;

use Amp\Loop\UvDriver;

/**
 * @requires extension uv
 */
class UvDriverTest extends DriverTest {
    public function getFactory(): callable {
        return function () {
            return new UvDriver;
        };
    }
}
