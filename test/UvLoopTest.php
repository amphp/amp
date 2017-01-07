<?php

namespace Amp\Test\Loop;

use Amp\Loop\UvLoop;
use AsyncInterop\Loop\DriverFactory;
use AsyncInterop\Loop\Test;

/**
 * @requires extension uv
 */
class UvLoopTest extends Test {
    public function getFactory() {
        $factory = $this->getMockBuilder(DriverFactory::class)->getMock();

        $factory->method('create')
            ->willReturn(new UvLoop);

        return $factory;
    }
}
