<?php

namespace Amp\Test\Loop;

use Amp\Loop\EventLoop;
use Interop\Async\Loop\DriverFactory;
use Interop\Async\Loop\Test;

/**
 * @requires extension event
 */
class EventLoopTest extends Test {
    public function getFactory() {
        if (!EventLoop::supported()) {
            $this->markTestSkipped("EventLoop is not available");
        }

        $factory = $this->getMockBuilder(DriverFactory::class)->getMock();

        $factory->method('create')
            ->willReturn(new EventLoop());

        return $factory;
    }
}
