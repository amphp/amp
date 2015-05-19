<?php

namespace Amp\Test;

use Amp\Promisor;
use Amp\Test\PromisorPublicImpl;

class PromisorPublicTest extends PromisorTest {
    protected function getPromisor($callbackData = null) {
        return new PromisorPublicImpl($callbackData);
    }

    public function testPromiseReturnsSelf() {
        $promisor = new PromisorPublicImpl;
        $this->assertSame($promisor, $promisor->promise());
    }
}
