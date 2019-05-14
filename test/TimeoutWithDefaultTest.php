<?php

namespace Amp\Test;

use Amp\Delayed;
use Amp\Failure;
use Amp\Loop;
use Amp\Promise;
use Amp\Success;
use function React\Promise\resolve;

class TimeoutWithDefaultTest extends BaseTest
{
    public function testSuccessfulPromise()
    {
        Loop::run(function () {
            $value = 1;

            $promise = new Success($value);

            $promise = Promise\timeoutWithDefault($promise, 100, 2);
            $this->assertInstanceOf(Promise::class, $promise);

            $callback = function ($exception, $value) use (&$result) {
                $result = $value;
            };

            $promise->onResolve($callback);

            $this->assertSame($value, $result);
        });
    }

    public function testFailedPromise()
    {
        Loop::run(function () {
            $exception = new \Exception;

            $promise = new Failure($exception);

            $promise = Promise\timeoutWithDefault($promise, 100, 2);
            $this->assertInstanceOf(Promise::class, $promise);

            $callback = function ($exception, $value) use (&$actual) {
                if ($exception) {
                    $actual = $exception;
                } else {
                    $actual = $value;
                }
            };

            $promise->onResolve($callback);

            $this->assertSame($exception, $actual);
        });
    }

    /**
     * @depends testSuccessfulPromise
     */
    public function testFastPending()
    {
        $value = 1;

        Loop::run(function () use (&$result, $value) {
            $promise = new Delayed(50, $value);

            $promise = Promise\timeoutWithDefault($promise, 100);
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
    public function testSlowPending()
    {
        $expected = 2;

        Loop::run(function () use (&$actual, $expected) {
            $promise = new Delayed(200);

            $promise = Promise\timeoutWithDefault($promise, 100, $expected);
            $this->assertInstanceOf(Promise::class, $promise);

            $callback = function ($exception, $value) use (&$actual) {
                $actual = $value;
            };

            $promise->onResolve($callback);
        });

        $this->assertEquals($expected, $actual);
    }

    /**
     * @depends testSuccessfulPromise
     */
    public function testReactPromise()
    {
        Loop::run(function () {
            $value = 1;

            $promise = resolve($value);

            $promise = Promise\timeoutWithDefault($promise, 100, 2);
            $this->assertInstanceOf(Promise::class, $promise);

            $callback = function ($exception, $value) use (&$result) {
                $result = $value;
            };

            $promise->onResolve($callback);

            $this->assertSame($value, $result);
        });
    }

    public function testNonPromise()
    {
        $this->expectException(\TypeError::class);
        Promise\timeoutWithDefault(42, 42);
    }
}
