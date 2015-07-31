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

    /**
     * We take care to cancel the signal watcher because Ev spazzes if
     * multiple watchers exist for the same signal in different loops
     */
    public function testOnSignalWatcherKeepAliveRunResult() {
        if (!\extension_loaded("pcntl")) {
            $this->markTestSkipped("ext/pcntl required to test onSignal() registration");
        }

        $watcherId = null;
        \Amp\run(function () use (&$watcherId) {
            $watcherId = \Amp\onSignal(SIGUSR1, function () {
                // empty
            }, $options = ["keep_alive" => false]);
        });

        \Amp\cancel($watcherId);
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
