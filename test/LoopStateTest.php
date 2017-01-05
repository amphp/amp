<?php

namespace AsyncInterop\Loop;

class LoopStateTest extends \PHPUnit_Framework_TestCase
{
    private $loop;

    protected function setUp()
    {
        $this->loop = $this->getMockForAbstractClass(Driver::class);
    }

    /** @test */
    public function defaultsToNull()
    {
        $this->assertNull($this->loop->getState("foobar"));
    }

    /**
     * @test
     * @dataProvider provideValues
     */
    public function getsPreviouslySetValue($value)
    {
        $this->loop->setState("foobar", $value);
        $this->assertSame($value, $this->loop->getState("foobar"));
    }

    public function provideValues()
    {
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
