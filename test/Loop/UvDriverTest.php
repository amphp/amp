<?php

namespace Amp\Test\Loop;

use Amp\Loop\UvDriver;

/**
 * @requires extension uv
 * @group uv
 */
class UvDriverTest extends DriverTest {
    public function getFactory(): callable {
        return function () {
            return new UvDriver;
        };
    }

    public function testHandle() {
        $this->assertInternalType('resource', $this->loop->getHandle());
    }

    public function testSupported() {
        $this->assertTrue(UvDriver::isSupported());
    }
}
