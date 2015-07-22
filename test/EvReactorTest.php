<?php

namespace Amp\Test;

use Amp\EvReactor;

class EvReactorTest extends ReactorTest {
    protected function getReactor() {
        if (extension_loaded("ev")) {
            return new EvReactor;
        } else {
            $this->markTestSkipped(
                "ev extension not loaded"
            );
        }
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
