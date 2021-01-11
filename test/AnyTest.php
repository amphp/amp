<?php

namespace Amp\Test;

use Amp\Delayed;
use Amp\Failure;
use Amp\Loop;
use Amp\Promise;
use Amp\Success;

class AnyTest extends BaseTest
{
    public function testEmptyArray(): void
    {
        $callback = function ($exception, $value) use (&$result) {
            $result = $value;
        };

        Promise\any([])->onResolve($callback);

        self::assertSame([[], []], $result);
    }

    public function testSuccessfulPromisesArray(): void
    {
        $promises = [new Success(1), new Success(2), new Success(3)];

        $callback = function ($exception, $value) use (&$result) {
            $result = $value;
        };

        Promise\any($promises)->onResolve($callback);

        self::assertSame([[], [1, 2, 3]], $result);
    }

    public function testFailedPromisesArray(): void
    {
        $exception = new \Exception;
        $promises = [new Failure($exception), new Failure($exception), new Failure($exception)];

        $callback = function ($exception, $value) use (&$result) {
            $result = $value;
        };

        Promise\any($promises)->onResolve($callback);

        self::assertSame([[$exception, $exception, $exception], []], $result);
    }

    public function testMixedPromisesArray(): void
    {
        $exception = new \Exception;
        $promises = [new Success(1), new Failure($exception), new Success(3)];

        $callback = function ($exception, $value) use (&$result) {
            $result = $value;
        };

        Promise\any($promises)->onResolve($callback);

        self::assertSame([[1 => $exception], [0 => 1, 2 => 3]], $result);
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

            Promise\any($promises)->onResolve($callback);
        });

        self::assertEquals([[], [1, 2, 3]], $result);
    }

    /**
     * @depends testMixedPromisesArray
     */
    public function testArrayKeysPreserved(): void
    {
        $exception = new \Exception;
        $expected = [['two' => $exception], ['one' => 1, 'three' => 3]];

        Loop::run(function () use (&$result, $exception) {
            $promises = [
                'one' => new Delayed(20, 1),
                'two' => new Failure($exception),
                'three' => new Delayed(10, 3),
            ];

            $callback = function ($exception, $value) use (&$result) {
                $result = $value;
            };

            Promise\any($promises)->onResolve($callback);
        });

        self::assertEquals($expected, $result);
    }

    public function testNonPromise(): void
    {
        $this->expectException(\TypeError::class);

        Promise\any([1]);
    }
}
