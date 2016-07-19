<?php

namespace Amp\Test;

class StructTestFixture {
    use \Amp\Struct;
    public $callback;
}

class StructTest extends \PHPUnit_Framework_TestCase {
    /**
     * @expectedException \DomainException
     * @expectedExceptionMessage Amp\Test\StructTestFixture property "callbac" does not exist ... did you mean "callback?"
     */
    public function testSetErrorWithSuggestion() {
        $struct = new StructTestFixture;
        $struct->callbac = function(){};
    }

    /**
     * @expectedException \DomainException
     * @expectedExceptionMessage Amp\Test\StructTestFixture property "callbac" does not exist ... did you mean "callback?"
     */
    public function testGetErrorWithSuggestion() {
        $struct = new StructTestFixture;
        $test = $struct->callbac;
    }

    /**
     * @expectedException \DomainException
     * @expectedExceptionMessage Amp\Test\StructTestFixture property "callZZZZZZZZZZZ" does not exist
     */
    public function testSetErrorWithoutSuggestion() {
        $struct = new StructTestFixture;
        $struct->callZZZZZZZZZZZ = "test";
    }

    /**
     * @expectedException \DomainException
     * @expectedExceptionMessage Amp\Test\StructTestFixture property "callZZZZZZZZZZZ" does not exist
     */
    public function testGetErrorWithoutSuggestion() {
        $struct = new StructTestFixture;
        $test = $struct->callZZZZZZZZZZZ;
    }

    /**
     * @expectedException \DomainException
     * @expectedExceptionMessage Amp\Test\StructTestFixture property "__propertySuggestThreshold" does not exist
     */
    public function testSuggestionIgnoresPropertyStartingWithUnderscore() {
        $struct = new StructTestFixture;
        $test = $struct->__propertySuggestThreshold;
    }
}
