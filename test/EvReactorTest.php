<?php

namespace Amp\Test;

use Amp\EvReactor;

class EvReactorTest extends ReactorTest {
    protected function getReactor() {
        if (extension_loaded("ev")) {
            return new EvReactor();
        } else {
            $this->markTestSkipped(
                "ev extension not loaded"
            );
        }
    }
}
