<?php

namespace Amp\Test;

class Promise {
    use \Amp\Internal\Placeholder {
        resolve as public;
        fail as public;
    }
}

class PromiseTest extends \Interop\Async\Promise\Test {
    public function promise() {
        $promise = new Promise;
        return [
            $promise,
            [$promise, 'resolve'],
            [$promise, 'fail'],
        ];
    }
}
