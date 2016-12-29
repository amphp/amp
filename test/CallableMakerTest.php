<?php

namespace Amp\Test;

class CallableMaker {
    use \Amp\CallableMaker {
        callableFromInstanceMethod as public;
        callableFromStaticMethod as public;
    }
    
    public function instanceMethod() {
        return __METHOD__;
    }
    
    public static function staticMethod() {
        return __METHOD__;
    }
}

class CallableMakerTest extends \PHPUnit_Framework_TestCase {
    /** @var \Amp\Test\CallableMaker */
    private $maker;

    public function setUp() {
        $this->maker = new CallableMaker;
    }

    public function testCallableFromInstanceMethod() {
        $callable = $this->maker->callableFromInstanceMethod("instanceMethod");
        $this->assertInternalType("callable", $callable);
        $this->assertSame(\sprintf("%s::%s", CallableMaker::class, "instanceMethod"), $callable());
    }
    
    public function testCallableFromStaticMethod() {
        $callable = $this->maker->callableFromInstanceMethod("staticMethod");
        $this->assertInternalType("callable", $callable);
        $this->assertSame(\sprintf("%s::%s", CallableMaker::class, "staticMethod"), $callable());
    }
}
