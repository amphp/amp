<?php

namespace Amp\Test;

use Amp\LibeventReactor;

class LibeventReactorTest extends ReactorTest {
    protected function getReactor() {
        if (extension_loaded('libevent')) {
            return new LibeventReactor;
        } else {
            $this->markTestSkipped(
                'libevent extension not loaded'
            );
        }
    }
}
