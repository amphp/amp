<?php

namespace Amp\Test;

use Amp\Delayed;
use Amp\Failure;
use Amp\Loop;
use Amp\Promise;
use Amp\Success;
use function React\Promise\resolve;

class TimeoutTest extends \PHPUnit\Framework\TestCase {
    public function testSuccessfulPromise() {
        Loop::run(function () {
            $value = 1;

            $promise = new Success($value);

            $promise = Promise\timeout($promise, 100);
            $this->assertInstanceOf(Promise::class, $promise);

            $callback = function ($exception, $value) use (&$result) {
                $result = $value;
            };

            $promise->onResolve($callback);

            $this->assertSame($value, $result);
        });
    }

    public function testFailedPromise() {
        Loop::run(function () {
            $exception = new \Exception;

            $promise = new Failure($exception);

            $promise = Promise\timeout($promise, 100);
            $this->assertInstanceOf(Promise::class, $promise);

            $callback = function ($exception, $value) use (&$reason) {
                $reason = $exception;
            };

            $promise->onResolve($callback);

            $this->assertSame($exception, $reason);
        });
    }

    /**
     * @depends testSuccessfulPromise
     */
    public function testFastPending() {
        $value = 1;

        Loop::run(function () use (&$result, $value) {
            $promise = new Delayed(50, $value);

            $promise = Promise\timeout($promise, 100);
            $this->assertInstanceOf(Promise::class, $promise);

            $callback = function ($exception, $value) use (&$result) {
                $result = $value;
            };

            $promise->onResolve($callback);
        });

        $this->assertSame($value, $result);
    }

    /**
     * @depends testSuccessfulPromise
     */
    public function testSlowPending() {
        Loop::run(function () use (&$reason) {
            $promise = new Delayed(200);

            $promise = Promise\timeout($promise, 100);
            $this->assertInstanceOf(Promise::class, $promise);

            $callback = function ($exception, $value) use (&$reason) {
                $reason = $exception;
            };

            $promise->onResolve($callback);
        });

        $this->assertInstanceOf(\Amp\TimeoutException::class, $reason);
    }

    /**
     * @depends testSuccessfulPromise
     */
    public function testReactPromise() {
        Loop::run(function () {
            $value = 1;

            $promise = resolve($value);

            $promise = Promise\timeout($promise, 100);
            $this->assertInstanceOf(Promise::class, $promise);

            $callback = function ($exception, $value) use (&$result) {
                $result = $value;
            };

            $promise->onResolve($callback);

            $this->assertSame($value, $result);
        });
    }

    public function testNonPromise() {
        $this->expectException(\TypeError::class);
        Promise\timeout(42, 42);
    }
}
