<?php

namespace Alert;

class NativeReactorTest extends ReactorTest {
    protected function getReactor() {
        return new NativeReactor;
    }
}
