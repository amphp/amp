<?php

namespace Amp\Test\Loop;

use Amp\Loop;
use Amp\Loop\DriverFoundation;
use PHPUnit\Framework\TestCase;

class DriverStateTest extends TestCase
{
    private DriverFoundation $loop;

    protected function setUp(): void
    {
        $this->loop = $this->getMockForAbstractClass(DriverFoundation::class);
    }

    /** @test */
    public function defaultsToNull(): void
    {
        $this->assertNull($this->loop->getState("foobar"));
    }

    /**
     * @test
     * @dataProvider provideValues
     */
    public function getsPreviouslySetValue($value): void
    {
        $this->loop->setState("foobar", $value);
        $this->assertSame($value, $this->loop->getState("foobar"));
    }

    /**
     * @test
     * @dataProvider provideValues
     */
    public function getsPreviouslySetValueViaAccessor($value): void
    {
        Loop::setState("foobar", $value);
        $this->assertSame($value, Loop::getState("foobar"));
    }

    public function provideValues(): array
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
