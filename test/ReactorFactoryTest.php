<?php

namespace Alert;

class ReactorFactoryTest extends \PHPUnit_Framework_TestCase {

    public function testSelectReturnsLibeventReactorIfExtensionLoaded() {
        if (!extension_loaded('libevent')) {
            $this->markTestSkipped(
                'ext/libevent extension not loaded'
            );
        }

        $rf = new ReactorFactory;
        $reactor = $rf->select();
        $this->assertInstanceOf('Alert\LibeventReactor', $reactor);
    }

    public function testMagicInvokeDelegatesToSelectMethod() {
        $rf = $this->getMock('Alert\ReactorFactory', ['select']);
        $rf->expects($this->once())
           ->method('select')
           ->will($this->returnValue(42));

        $this->assertEquals(42, $rf->__invoke());
    }
}
