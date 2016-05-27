<?php

namespace Interop\Async\Loop;

class RegistryTest extends \PHPUnit_Framework_TestCase
{
    private $registry;

    protected function setUp()
    {
        $this->registry = $this->getMockForTrait(Registry::class);
    }

    /** @test */
    public function defaultsToNull()
    {
        $this->assertNull($this->registry->fetchState("foobar"));
    }

    /**
     * @test
     * @dataProvider provideValues
     */
    public function fetchesStoredValue($value)
    {
        $this->assertNull($this->registry->fetchState("foobar"));
        $this->registry->storeState("foobar", $value);

        $this->assertSame($value, $this->registry->fetchState("foobar"));
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
