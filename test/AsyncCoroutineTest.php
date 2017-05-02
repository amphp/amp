<?php

namespace Amp\Test;

use Amp;
use Amp\Coroutine;
use Amp\Failure;
use Amp\Loop;
use Amp\Promise;
use Amp\Success;
use PHPUnit\Framework\TestCase;
use React\Promise\FulfilledPromise as FulfilledReactPromise;

class AsyncCoroutineTest extends TestCase {
    public function testWithFailure() {
        $coroutine = Amp\asyncCoroutine(function ($value) {
            return new Failure(new Amp\PHPUnit\TestException);
        });

        $coroutine(42);

        $this->expectException(Amp\PHPUnit\TestException::class);

        Loop::run();
    }
}
