<?php

namespace Amp\Test\Loop;

use Amp\Loop\NativeDriver;
use Amp\Loop\TracingDriver;

class TracingDriverTest extends DriverTest
{
    public function getFactory(): callable
    {
        return static function () {
            return new TracingDriver(new NativeDriver);
        };
    }

    /**
     * @dataProvider provideRegistrationArgs
     * @group memoryleak
     */
    public function testNoMemoryLeak($type, $args)
    {
        // Skip, because the driver intentionally leaks
        $this->assertTrue(true);
    }
}
