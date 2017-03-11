<?php

namespace Amp\Test;

use Amp\Loop\Driver;

class LoopStateTest extends \PHPUnit\Framework\TestCase {
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
