<?php

namespace Amp\Test;

use PHPUnit\Framework\TestCase;

class StructTestFixture
{
    use \Amp\Struct;
    public $callback;
    public $_foofoofoofoofoofoofoofoobar;
}

class StructTest extends TestCase
{
    /**
     * @expectedException \Error
     * @expectedExceptionMessage Amp\Test\StructTestFixture property 'callbac' does not exist ... did you mean
     *     "callback?"
     */
    public function testSetErrorWithSuggestion(): void
    {
        $struct = new StructTestFixture;
        $struct->callbac = function () {
        };
    }

    /**
     * @expectedException \Error
     * @expectedExceptionMessage Amp\Test\StructTestFixture property 'callbac' does not exist ... did you mean
     *     "callback?"
     */
    public function testGetErrorWithSuggestion(): void
    {
        $struct = new StructTestFixture;
        $test = $struct->callbac;
    }

    /**
     * @expectedException \Error
     * @expectedExceptionMessage Amp\Test\StructTestFixture property 'callZZZZZZZZZZZ' does not exist
     */
    public function testSetErrorWithoutSuggestion(): void
    {
        $struct = new StructTestFixture;
        $struct->callZZZZZZZZZZZ = "test";
    }

    /**
     * @expectedException \Error
     * @expectedExceptionMessage Amp\Test\StructTestFixture property 'callZZZZZZZZZZZ' does not exist
     */
    public function testGetErrorWithoutSuggestion(): void
    {
        $struct = new StructTestFixture;
        $test = $struct->callZZZZZZZZZZZ;
    }

    /**
     * @expectedException \Error
     * @expectedExceptionMessage Amp\Test\StructTestFixture property '__propertySuggestThreshold' does not exist
     */
    public function testSuggestionIgnoresPropertyStartingWithUnderscore(): void
    {
        $struct = new StructTestFixture;
        $struct->__propertySuggestThreshold;
    }

    public function testSetErrorWithoutSuggestionBecauseUnderscore(): void
    {
        // Use regexp to ensure no property is suggested, because expected message is a prefix then and still passes
        $this->expectException(\Error::class);
        $this->expectExceptionMessageRegExp("(Amp\\\\Test\\\\StructTestFixture property 'foofoofoofoofoofoofoofoobar' does not exist$)");

        $struct = new StructTestFixture;
        $struct->foofoofoofoofoofoofoofoobar = "test";
    }

    public function testGetErrorWithoutSuggestionBecauseUnderscore(): void
    {
        // Use regexp to ensure no property is suggested, because expected message is a prefix then and still passes
        $this->expectException(\Error::class);
        $this->expectExceptionMessageRegExp("(Amp\\\\Test\\\\StructTestFixture property 'foofoofoofoofoofoofoofoobar' does not exist$)");

        $struct = new StructTestFixture;
        $struct->foofoofoofoofoofoofoofoobar;
    }
}
