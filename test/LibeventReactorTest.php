<?php

namespace Amp\Test;

use Amp\LibeventReactor;

class LibeventReactorTest extends ReactorTest {
    protected function setUp() {
        if (extension_loaded("libevent")) {
            \Amp\reactor($assign = new LibeventReactor);
        } else {
            $this->markTestSkipped(
                "libevent extension not loaded"
            );
        }
    }

    public function testGetLoop() {
        $result = \Amp\reactor()->getLoop();
        $this->assertInternalType("resource", $result);
    }
}
