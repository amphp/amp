<?php

namespace Amp\Test;

class Promise implements \Amp\Promise {
    use \Amp\Internal\Placeholder {
        resolve as public;
        fail as public;
    }
}

class PromiseTest extends \Amp\Promise\Test {
    public function promise() {
        $promise = new Promise;
        return [
            $promise,
            [$promise, 'resolve'],
            [$promise, 'fail'],
        ];
    }

    public function testWhenQueueUnrolling() {
        $count = 50;
        $invoked = false;

        $promise = new Promise;
        $promise->when(function () { });
        $promise->when(function () { });
        $promise->when(function () use (&$invoked) {
            $invoked = true;
            $this->assertLessThan(30, count(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)));
        });

        $last = $promise;

        $f = function () use (&$f, &$count, &$last) {
            $p = new Promise;
            $p->when(function () { });
            $p->when(function () { });

            $last->resolve($p);
            $last = $p;

            if (--$count > 0) {
                $f();
            }
        };

        $f();
        $last->resolve();

        $this->assertTrue($invoked);
    }
}
