<?php

namespace Amp\Test\Loop;

use Amp\Loop\EvLoop;
use AsyncInterop\Loop\DriverFactory;
use AsyncInterop\Loop\Test;

/**
 * @requires extension ev
 */
class EvLoopTest extends Test {
    public function getFactory() {
        $factory = $this->getMockBuilder(DriverFactory::class)->getMock();

        $factory->method('create')
            ->willReturn(new EvLoop);

        return $factory;
    }
}
