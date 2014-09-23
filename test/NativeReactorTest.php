<?php

namespace Amp\Test;

use Amp\NativeReactor;

class NativeReactorTest extends ReactorTest {
    protected function getReactor() {
        return new NativeReactor;
    }
}
