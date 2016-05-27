<?php

namespace Amp\Test\Loop;

use Amp\Loop\NativeLoop;
use Interop\Async\Loop\DriverFactory;
use Interop\Async\Loop\Test;

class NativeLoopTest extends Test {
    public function getFactory() {
        $factory = $this->getMockBuilder(DriverFactory::class)->getMock();

        $factory->method('create')
            ->willReturn(new NativeLoop());

        return $factory;
    }
}
