<?php

namespace Amp\Test;

class PlaceholderPublicTest extends PlaceholderTest {
    protected function getPromisor() {
        return new PromisorPublicImpl;
    }
}
