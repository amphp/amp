<?php

namespace Alert;

class LibeventReactorTest extends ReactorTest {
    protected function getReactor() {
        if (extension_loaded('libevent')) {
            return new LibeventReactor;
        } else {
            $this->markTestSkipped(
                'ext/libevent extension not loaded'
            );
        }
    }
}
