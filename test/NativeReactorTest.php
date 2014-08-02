<?php

namespace AlertTest;

use Alert\NativeReactor;

class NativeReactorTest extends ReactorTest {
    protected function getReactor() {
        return new NativeReactor;
    }
}
