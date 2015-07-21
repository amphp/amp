<?php

namespace Amp\Test;

use Amp\Promisor;
use Amp\Test\PromisorPublicImpl;

class PromisorPublicTest extends PromisorTest {
    protected function getPromisor() {
        return new PromisorPublicImpl;
    }

    public function testPromiseReturnsSelf() {
        $promisor = new PromisorPublicImpl;
        $this->assertSame($promisor, $promisor->promise());
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Throwable Exception instance required to fail a promise
     * @dataProvider provideBadFailureArguments
     */
    public function testResolvingErrorWithNonExceptionThrows($badArg) {
        $promisor = $this->getPromisor();
        $promisor->fail($badArg);
    }

    public function provideBadFailureArguments() {
        return [
            [1],
            [true],
            [new \StdClass],
        ];
    }
}
