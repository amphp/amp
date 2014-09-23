<?php

namespace Amp\Test;

use Amp\UvReactor;

class UvReactorTest extends ReactorTest {
    protected function getReactor() {
        if (extension_loaded('uv')) {
            return new UvReactor($new = TRUE);
        } else {
            $this->markTestSkipped(
                'php-uv extension not loaded'
            );
        }
    }
}
