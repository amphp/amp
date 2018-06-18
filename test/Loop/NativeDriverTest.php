<?php

namespace Amp\Test\Loop;

use Amp\Loop\NativeDriver;

class NativeDriverTest extends DriverTest
{
    public function getFactory(): callable
    {
        return function () {
            return new NativeDriver;
        };
    }

    public function testHandle()
    {
        $this->assertNull($this->loop->getHandle());
    }
}
