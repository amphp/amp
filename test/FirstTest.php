<?php

namespace Amp\Test;

use Amp\Delayed;
use Amp\Failure;
use Amp\Loop;
use Amp\MultiReasonException;
use Amp\Promise;
use Amp\Success;
use React\Promise\FulfilledPromise;

class FirstTest extends BaseTest
{
    public function testEmptyArray(): void
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('No promises provided');

        Promise\first([]);
    }

    public function testSuccessfulPromisesArray(): void
    {
        $promises = [new Success(1), new Success(2), new Success(3)];

        $callback = function ($exception, $value) use (&$result) {
            $result = $value;
        };

        Promise\first($promises)->onResolve($callback);

        self::assertSame(1, $result);
    }

    public function testFailedPromisesArray(): void
    {
        $exception = new \Exception;
        $promises = [new Failure($exception), new Failure($exception), new Failure($exception)];

        $callback = function ($exception, $value) use (&$reason) {
            $reason = $exception;
        };

        Promise\first($promises)->onResolve($callback);

        self::assertInstanceOf(MultiReasonException::class, $reason);
        self::assertSame([$exception, $exception, $exception], $reason->getReasons());
    }

    public function testMixedPromisesArray(): void
    {
        $exception = new \Exception;
        $promises = [new Failure($exception), new Failure($exception), new Success(3)];

        $callback = function ($exception, $value) use (&$result) {
            $result = $value;
        };

        Promise\first($promises)->onResolve($callback);

        self::assertSame(3, $result);
    }

    public function testReactPromiseArray(): void
    {
        $promises = [new FulfilledPromise(1), new FulfilledPromise(2), new Success(3)];

        $callback = function ($exception, $value) use (&$result) {
            $result = $value;
        };

        Promise\first($promises)->onResolve($callback);

        self::assertSame(1, $result);
    }

    public function testPendingPromiseArray(): void
    {
        Loop::run(function () use (&$result) {
            $promises = [
                new Delayed(20, 1),
                new Delayed(30, 2),
                new Delayed(10, 3),
            ];

            $callback = function ($exception, $value) use (&$result) {
                $result = $value;
            };

            Promise\first($promises)->onResolve($callback);
        });

        self::assertSame(3, $result);
    }

    public function testNonPromise(): void
    {
        $this->expectException(\TypeError::class);

        Promise\first([1]);
    }
}
