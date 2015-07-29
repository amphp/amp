<?php

namespace Amp\Test;

use Amp\NativeReactor;

class NativeReactorTest extends ReactorTest {
    protected function setUp() {
        \Amp\reactor($assign = new NativeReactor);
    }

    public function testOnSignalWatcher() {
        if (!\extension_loaded("pcntl")) {
            $this->markTestSkipped(
                "ext/pcntl required to test onSignal() capture"
            );
        } else {
            parent::testOnSignalWatcher();
        }
    }

    public function testInitiallyDisabledOnSignalWatcher() {
        if (!\extension_loaded("pcntl")) {
            $this->markTestSkipped(
                "ext/pcntl required to test onSignal() capture"
            );
        } else {
            parent::testInitiallyDisabledOnSignalWatcher();
        }
    }
}
