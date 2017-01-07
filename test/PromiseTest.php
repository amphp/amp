<?php

namespace Amp\Test;

class Promise {
    use \Amp\Internal\Placeholder {
        resolve as public;
        fail as public;
    }
}

class PromiseTest extends \AsyncInterop\Promise\Test {
    public function promise() {
        $promise = new Promise;
        return [
            $promise,
            [$promise, 'resolve'],
            [$promise, 'fail'],
        ];
    }
}
