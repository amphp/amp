<?php

namespace Interop\Async;

class RegistryTest extends \PHPUnit_Framework_TestCase
{
    use Registry;

    protected function setUp()
    {
        self::$registry = null;
    }

    /**
     * @test
     * @expectedException \RuntimeException
     */
    public function fetchfailsOutsideOfLoop()
    {
        self::fetchState("foobar");
    }

    /**
     * @test
     * @expectedException \RuntimeException
     */
    public function storefailsOutsideOfLoop()
    {
        self::fetchState("store");
    }

    /** @test */
    public function defaultsToNull()
    {
        // emulate we're in an event loop…
        self::$registry = [];
        $this->assertNull(self::fetchState("foobar"));
    }

    /**
     * @test
     * @dataProvider provideValues
     */
    public function fetchesStoredValue($value)
    {
        // emulate we're in an event loop…
        self::$registry = [];

        $this->assertNull(self::fetchState("foobar"));
        self::storeState("foobar", $value);

        $this->assertSame($value, self::fetchState("foobar"));
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
