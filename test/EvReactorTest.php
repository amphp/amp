<?php

namespace Amp\Test;

use Amp\EvReactor;

class EvReactorTest extends ReactorTest {
    protected function setUp() {
        if (extension_loaded("ev")) {
            \Amp\reactor($assign = new EvReactor);
        } else {
            $this->markTestSkipped(
                "ev extension not loaded"
            );
        }
    }

    public function testGetLoop() {
        $result = \Amp\reactor()->getLoop();
        $this->assertInstanceOf("EvLoop", $result);
    }

    public function testImmediateCoroutineResolutionError() {
        if (\extension_loaded("xdebug")) {
            $this->markTestSkipped(
                "Cannot run this test with xdebug enabled: it causes zend_mm_heap corrupted"
            );
        } else {
            parent::testImmediateCoroutineResolutionError();
        }
    }

    public function testOnErrorFailure() {
        if (\extension_loaded("xdebug")) {
            $this->markTestSkipped(
                "Cannot run this test with xdebug enabled: it causes zend_mm_heap corrupted"
            );
        } else {
            parent::testImmediateCoroutineResolutionError();
        }
    }
}
