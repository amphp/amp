<?php

namespace Amp\Test\Loop;

use Amp\Loop\NativeLoop;
use AsyncInterop\Loop\DriverFactory;
use AsyncInterop\Loop\Test;

class NativeLoopTest extends Test {
    public function getFactory() {
        $factory = $this->getMockBuilder(DriverFactory::class)->getMock();

        $factory->method('create')
            ->willReturn(new NativeLoop());

        return $factory;
    }
}
