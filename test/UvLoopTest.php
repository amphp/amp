<?php

namespace Amp\Test\Loop;

use Amp\Loop\UvLoop;
use Interop\Async\Loop\DriverFactory;
use Interop\Async\Loop\Test;

/**
 * @requires extension uv
 */
class UvLoopTest extends Test {
    public function getFactory() {
        $factory = $this->getMockBuilder(DriverFactory::class)->getMock();

        $factory->method('create')
            ->willReturn(new UvLoop());

        return $factory;
    }
}
