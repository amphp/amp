<?php

namespace Amp\Test\Loop;

use Amp\Loop;
use Amp\Loop\Driver;
use PHPUnit\Framework\TestCase;

class DriverStateTest extends TestCase {
    /** @var Driver */
    private $loop;

    protected function setUp() {
        $this->loop = $this->getMockForAbstractClass(Driver::class);
    }

    /** @test */
    public function defaultsToNull() {
        $this->assertNull($this->loop->getState("foobar"));
    }

    /**
     * @test
     * @dataProvider provideValues
     */
    public function getsPreviouslySetValue($value) {
        $this->loop->setState("foobar", $value);
        $this->assertSame($value, $this->loop->getState("foobar"));
    }

    /**
     * @test
     * @dataProvider provideValues
     */
    public function getsPreviouslySetValueViaAccessor($value) {
        Loop::setState("foobar", $value);
        $this->assertSame($value, Loop::getState("foobar"));
    }

    public function provideValues() {
        return [
            ["string"],
            [42],
            [1.001],
            [true],
            [false],
            [null],
            [new \StdClass],
        ];
    }
}
