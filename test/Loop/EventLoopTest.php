<?php

namespace Amp\Test\Loop;

use Amp\Loop\EventDriver;

/**
 * @requires extension event
 */
class EventDriverTest extends DriverTest {
    public function getFactory(): callable {
        return function () {
            return new EventDriver;
        };
    }
}
