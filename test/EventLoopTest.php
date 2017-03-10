<?php

namespace Amp\Test\Loop;

use Amp\Loop\EventLoop;
use AsyncInterop\Loop\DriverFactory;
use AsyncInterop\Loop\Test;

/**
 * @requires extension event
 */
class EventLoopTest extends Test {
    public function getFactory() {
        $factory = $this->getMockBuilder(DriverFactory::class)->getMock();

        $factory->method('create')
            ->willReturn(new EventLoop);

        return $factory;
    }
}
