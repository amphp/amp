<?php

namespace Amp\Test;

class StructTestFixture {
    use \Amp\Struct;
    public $callback;
    public $_foofoofoofoofoofoofoofoobar;
}

class StructTest extends \PHPUnit_Framework_TestCase {
    /**
     * @expectedException \Error
     * @expectedExceptionMessage Amp\Test\StructTestFixture property "callbac" does not exist ... did you mean "callback?"
     */
    public function testSetErrorWithSuggestion() {
        $struct = new StructTestFixture;
        $struct->callbac = function(){};
    }

    /**
     * @expectedException \Error
     * @expectedExceptionMessage Amp\Test\StructTestFixture property "callbac" does not exist ... did you mean "callback?"
     */
    public function testGetErrorWithSuggestion() {
        $struct = new StructTestFixture;
        $test = $struct->callbac;
    }

    /**
     * @expectedException \Error
     * @expectedExceptionMessage Amp\Test\StructTestFixture property "callZZZZZZZZZZZ" does not exist
     */
    public function testSetErrorWithoutSuggestion() {
        $struct = new StructTestFixture;
        $struct->callZZZZZZZZZZZ = "test";
    }

    /**
     * @expectedException \Error
     * @expectedExceptionMessage Amp\Test\StructTestFixture property "callZZZZZZZZZZZ" does not exist
     */
    public function testGetErrorWithoutSuggestion() {
        $struct = new StructTestFixture;
        $test = $struct->callZZZZZZZZZZZ;
    }

    /**
     * @expectedException \Error
     * @expectedExceptionMessage Amp\Test\StructTestFixture property "__propertySuggestThreshold" does not exist
     */
    public function testSuggestionIgnoresPropertyStartingWithUnderscore() {
        $struct = new StructTestFixture;
        $test = $struct->__propertySuggestThreshold;
    }

    public function testSetErrorWithoutSuggestionBecauseUnderscore() {
        // Use regexp to ensure no property is suggested, because expected message is a prefix then and still passes
        $this->setExpectedExceptionRegExp(\Error::class, "(Amp\\\\Test\\\\StructTestFixture property \"foofoofoofoofoofoofoofoobar\" does not exist$)");
        $struct = new StructTestFixture;
        $struct->foofoofoofoofoofoofoofoobar = "test";
    }

    public function testGetErrorWithoutSuggestionBecauseUnderscore() {
        // Use regexp to ensure no property is suggested, because expected message is a prefix then and still passes
        $this->setExpectedExceptionRegExp(\Error::class, "(Amp\\\\Test\\\\StructTestFixture property \"foofoofoofoofoofoofoofoobar\" does not exist$)");
        $struct = new StructTestFixture;
        $test = $struct->foofoofoofoofoofoofoofoobar;
    }
}
