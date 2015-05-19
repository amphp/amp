<?php

namespace Amp\Test;

class PlaceholderPrivateTest extends PlaceholderTest {
    protected function getPromisor() {
        return new PromisorPrivateImpl;
    }
}
