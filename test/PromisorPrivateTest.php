<?php

namespace Amp\Test;

use Amp\Promisor;
use Amp\Test\PromisorPrivateImpl;

class PromisorPrivateTest extends PromisorTest {
    protected function getPromisor() {
        return new PromisorPrivateImpl;
    }
}
