<?php

namespace Amp\Test\Loop;

use Amp\Loop\UvDriver;

/**
 * @requires extension uv
 */
class UvDriverTest extends DriverTest
{
    /**
     * Timing mechanisms between drivers vary on accuracy. Set the target resolution in milliseconds.
     *
     * @var int
     */
    const DELAY_TEST_RESOLUTION_MS = 10;

    /**
     * Allow elapsed time assertions to be epsilon ms less than DELAY_TEST_MIN_RESOLUTION_MS.
     *
     * @var int
     */
    const DELAY_TEST_EPSILON_MS = 1;

    public function getFactory(): callable
    {
        return function () {
            return new UvDriver;
        };
    }

    public function testHandle()
    {
        $handle = $this->loop->getHandle();
        $this->assertTrue(\is_resource($handle) || $handle instanceof \UVLoop);
    }

    public function testSupported()
    {
        $this->assertTrue(UvDriver::isSupported());
    }
}
