<?php

namespace Amp\Test;

use Amp\Delayed;
use Amp\Failure;
use Amp\Loop;
use Amp\MultiReasonException;
use Amp\Promise;
use Amp\Success;
use React\Promise\FulfilledPromise;

class SomeTest extends BaseTest
{
    public function testEmptyArray(): void
    {
        $this->assertSame([[], []], Promise\wait(Promise\some([], 0)));
    }

    public function testEmptyArrayWithNonZeroRequired(): void
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Too few promises provided');

        Promise\some([], 1);
    }

    public function testInvalidRequiredNumberOfPromises(): void
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('non-negative');

        Promise\some([], -1);
    }

    public function testSuccessfulPromisesArray(): void
    {
        $promises = [new Success(1), new Success(2), new Success(3)];

        $callback = function ($exception, $value) use (&$result) {
            $result = $value;
        };

        Promise\some($promises)->onResolve($callback);

        $this->assertSame([[], [1, 2, 3]], $result);
    }

    public function testReactPromiseArray(): void
    {
        $promises = [new FulfilledPromise(1), new FulfilledPromise(2), new Success(3)];

        $callback = function ($exception, $value) use (&$result) {
            $result = $value;
        };

        Promise\some($promises)->onResolve($callback);

        $this->assertSame([[], [1, 2, 3]], $result);
    }

    public function testFailedPromisesArray(): void
    {
        $exception = new \Exception;
        $promises = [new Failure($exception), new Failure($exception), new Failure($exception)];

        $callback = function ($exception, $value) use (&$reason) {
            $reason = $exception;
        };

        Promise\some($promises)->onResolve($callback);

        $this->assertInstanceOf(MultiReasonException::class, $reason);
        $this->assertSame([$exception, $exception, $exception], $reason->getReasons());
    }

    public function testSuccessfulAndFailedPromisesArray(): void
    {
        $exception = new \Exception;
        $promises = [new Failure($exception), new Failure($exception), new Success(3)];

        $callback = function ($exception, $value) use (&$result) {
            $result = $value;
        };

        Promise\some($promises)->onResolve($callback);

        $this->assertSame([[0 => $exception, 1 => $exception], [2 => 3]], $result);
    }

    public function testPendingAwatiablesArray(): void
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

            Promise\some($promises)->onResolve($callback);
        });

        $this->assertEquals([[], [0 => 1, 1 => 2, 2 => 3]], $result);
    }

    public function testArrayKeysPreserved(): void
    {
        $expected = [[], ['one' => 1, 'two' => 2, 'three' => 3]];

        Loop::run(function () use (&$result) {
            $promises = [
                'one' => new Delayed(20, 1),
                'two' => new Delayed(30, 2),
                'three' => new Delayed(10, 3),
            ];

            $callback = function ($exception, $value) use (&$result) {
                $result = $value;
            };

            Promise\some($promises)->onResolve($callback);
        });

        $this->assertEquals($expected, $result);
    }

    public function testNonPromise(): void
    {
        $this->expectException(\Error::class);

        Promise\some([1]);
    }
}
