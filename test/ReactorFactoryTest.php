<?php

namespace Alert;

class ReactorFactoryTest extends \PHPUnit_Framework_TestCase {
    /**
     * @requires extension libevent
     */
    public function testSelectReturnsLibeventReactorIfExtensionLoaded() {
        $rf = new ReactorFactory;
        $reactor = $rf->select();
        $this->assertInstanceOf('Alert\LibeventReactor', $reactor);
    }

    /**
     * @requires extension uv
     */
    public function testSelectReturnsUvReactorIfExtensionLoaded() {
        $rf = new ReactorFactory;
        $reactor = $rf->select();
        $this->assertInstanceOf('Alert\UvReactor', $reactor);
    }

    public function testMagicInvokeDelegatesToSelectMethod() {
        $rf = $this->getMock('Alert\ReactorFactory', ['select']);
        $rf->expects($this->once())
           ->method('select')
           ->will($this->returnValue(42));

        $this->assertEquals(42, $rf->__invoke());
    }
}
