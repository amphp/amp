<?php

namespace Amp\Test;

use Amp\Deferred;
use Amp\Failure;
use Amp\Loop;
use Amp\Pause;
use Amp\Promise;
use Amp\Success;
use PHPUnit\Framework\TestCase;
use function React\Promise\resolve;

class WaitTest extends TestCase {
    public function testWaitOnSuccessfulPromise() {
        $value = 1;

        $promise = new Success($value);

        $result = Promise\wait($promise);

        $this->assertSame($value, $result);
    }

    public function testWaitOnFailedPromise() {
        $exception = new \Exception();

        $promise = new Failure($exception);

        try {
            $result = Promise\wait($promise);
        } catch (\Exception $e) {
            $this->assertSame($exception, $e);
            return;
        }

        $this->fail('Rejection exception should be thrown from wait().');
    }

    /**
     * @depends testWaitOnSuccessfulPromise
     */
    public function testWaitOnPendingPromise() {
        Loop::run(function () {
            $value = 1;

            $promise = new Pause(100, $value);

            $result = Promise\wait($promise);

            $this->assertSame($value, $result);
        });
    }

    /**
     * @expectedException \Error
     * @expectedExceptionMessage Loop stopped without resolving the promise
     */
    public function testPromiseWithNoResolutionPathThrowsException() {
        Promise\wait((new Deferred)->promise());
    }

    /**
     * @depends testWaitOnSuccessfulPromise
     */
    public function testReactPromise() {
        $value = 1;

        $promise = resolve($value);

        $result = Promise\wait($promise);

        $this->assertSame($value, $result);
    }

    public function testNonPromise() {
        $this->expectException(\TypeError::class);
        Promise\wait(42);
    }
}
