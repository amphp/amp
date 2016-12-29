<?php

namespace Amp\Test\Loop;

use Amp\Loop\EvLoop;
use Interop\Async\Loop\DriverFactory;
use Interop\Async\Loop\Test;

/**
 * @requires extension ev
 */
class EvLoopTest extends Test {
    public function getFactory() {
        if (!EvLoop::supported()) {
            $this->markTestSkipped("EvLoop is not available");
        }

        $factory = $this->getMockBuilder(DriverFactory::class)->getMock();

        $factory->method('create')
            ->willReturn(new EvLoop());

        return $factory;
    }
}
