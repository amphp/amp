<?php

namespace Amp\Test;

use Amp\UvReactor;

class UvReactorTest extends ReactorTest {
    protected function setUp() {
        if (extension_loaded("uv")) {
            \Amp\reactor($assign = new UvReactor);
        } else {
            $this->markTestSkipped(
                "php-uv extension not loaded"
            );
        }
    }

    public function testGetLoop() {
        $result = \Amp\reactor()->getLoop();
        $this->assertInternalType("resource", $result);
    }
}
