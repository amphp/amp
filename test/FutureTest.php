<?php

namespace Amp\Test;

use Amp\Future;

class FutureTest extends PromisorTest {
    protected function getPromisor() {
        return new Future;
    }
    public function testPromiseReturnsSelf() {
        $promisor = new Future;
        $this->assertSame($promisor, $promisor->promise());
    }
}
