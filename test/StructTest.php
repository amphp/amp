<?php

namespace Amp\Test;

use Amp\Watcher;

class StructTest extends \PHPUnit_Framework_TestCase {
    /**
     * @expectedException \DomainException
     * @expectedExceptionMessage Amp\Watcher property "callbac" does not exist ... did you mean "callback?"
     */
    public function testSetErrorWithSuggestion() {
        $struct = new Watcher;
        $struct->callbac = function(){};
    }
    
    /**
     * @expectedException \DomainException
     * @expectedExceptionMessage Amp\Watcher property "callbac" does not exist ... did you mean "callback?"
     */
    public function testGetErrorWithSuggestion() {
        $struct = new Watcher;
        $test = $struct->callbac;
    }

    /**
     * @expectedException \DomainException
     * @expectedExceptionMessage Amp\Watcher property "callZZZZZZZZZZZ" does not exist
     */
    public function testSetErrorWithoutSuggestion() {
        $struct = new Watcher;
        $struct->callZZZZZZZZZZZ = "test";
    }
    
    /**
     * @expectedException \DomainException
     * @expectedExceptionMessage Amp\Watcher property "callZZZZZZZZZZZ" does not exist
     */
    public function testGetErrorWithoutSuggestion() {
        $struct = new Watcher;
        $test = $struct->callZZZZZZZZZZZ;
    }

    /**
     * @expectedException \DomainException
     * @expectedExceptionMessage Amp\Watcher property "__propertySuggestThreshold" does not exist
     */
    public function testSuggestionIgnoresPropertyStartingWithUnderscore() {
        $struct = new Watcher;
        $test = $struct->__propertySuggestThreshold;
    }
}
