<?php

namespace Amp\Test;

class StructTestFixture
{
    use \Amp\Struct;

    public $callback;
    public $_foofoofoofoofoofoofoofoobar;
}

class StructTest extends BaseTest
{
    public function testSetErrorWithSuggestion(): void
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Amp\Test\StructTestFixture property "callbac" does not exist ... did you mean "callback?"');

        $struct = new StructTestFixture;
        $struct->callbac = function () {
        };
    }

    public function testGetErrorWithSuggestion(): void
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Amp\Test\StructTestFixture property "callbac" does not exist ... did you mean "callback?"');

        $struct = new StructTestFixture;
        $test = $struct->callbac;
    }

    public function testSetErrorWithoutSuggestion(): void
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Amp\Test\StructTestFixture property "callZZZZZZZZZZZ" does not exist');

        $struct = new StructTestFixture;
        $struct->callZZZZZZZZZZZ = "test";
    }

    public function testGetErrorWithoutSuggestion(): void
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Amp\Test\StructTestFixture property "callZZZZZZZZZZZ" does not exist');

        $struct = new StructTestFixture;
        $test = $struct->callZZZZZZZZZZZ;
    }

    public function testSuggestionIgnoresPropertyStartingWithUnderscore(): void
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Amp\Test\StructTestFixture property "__propertySuggestThreshold" does not exist');

        $struct = new StructTestFixture;
        $struct->__propertySuggestThreshold;
    }

    public function testSetErrorWithoutSuggestionBecauseUnderscore(): void
    {
        // Use regexp to ensure no property is suggested, because expected message is a prefix then and still passes
        $this->expectException(\Error::class);
        $this->expectDeprecationMessageMatches("(Amp\\\\Test\\\\StructTestFixture property \"foofoofoofoofoofoofoofoobar\" does not exist$)");

        $struct = new StructTestFixture;
        $struct->foofoofoofoofoofoofoofoobar = "test";
    }

    public function testGetErrorWithoutSuggestionBecauseUnderscore(): void
    {
        // Use regexp to ensure no property is suggested, because expected message is a prefix then and still passes
        $this->expectException(\Error::class);
        $this->expectDeprecationMessageMatches("(Amp\\\\Test\\\\StructTestFixture property \"foofoofoofoofoofoofoofoobar\" does not exist$)");

        $struct = new StructTestFixture;
        $struct->foofoofoofoofoofoofoofoobar;
    }
}
