<?php

namespace Amp\Test;

use Amp\LibeventReactor;

class LibeventReactorTest extends ReactorTest {
    protected function getReactor() {
        return new LibeventReactor;
    }
}
