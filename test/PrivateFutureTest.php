<?php

namespace Amp\Test;

use Amp\PrivateFuture;

class PrivateFutureTest extends PromisorTest {
    protected function getPromisor() {
        return new PrivateFuture;
    }
}
