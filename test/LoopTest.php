<?php

namespace Interop\Async\Loop;

use Interop\Async\Loop;
use Interop\Async\LoopDriver;
use Interop\Async\LoopDriverFactory;

class LoopTest extends \PHPUnit_Framework_TestCase
{
    protected function setUp() {
        Loop::setFactory(null);
    }

    /**
     * @test
     * @expectedException \RuntimeException
     * @expectedExceptionMessage new factory while running isn't allowed
     */
    public function setFactoryFailsIfRunning() {
        $driver = $this->getMockBuilder(LoopDriver::class)->getMock();

        $factory = $this->getMockBuilder(LoopDriverFactory::class)->getMock();
        $factory->method("create")->willReturn($driver);

        Loop::setFactory($factory);

        Loop::execute(function () use ($factory) {
            Loop::setFactory($factory);
        });
    }

    /** @test */
    public function executeStackReturnsScopedDriver() {
        $driver1 = $this->getMockBuilder(LoopDriver::class)->getMock();
        $driver2 = $this->getMockBuilder(LoopDriver::class)->getMock();

        Loop::execute(function () use ($driver1, $driver2) {
            $this->assertSame($driver1, Loop::get());

            Loop::execute(function () use ($driver2) {
                $this->assertSame($driver2, Loop::get());
            }, $driver2);

            $this->assertSame($driver1, Loop::get());
        }, $driver1);
    }
}
