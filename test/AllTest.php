<?php

namespace Amp\Test;

use Amp\Delayed;
use Amp\Loop;
use Amp\Promise;
use Amp\Success;
use React\Promise\FulfilledPromise;

class AllTest extends BaseTest
{
    public function testEmptyArray(): void
    {
        $callback = function ($exception, $value) use (&$result) {
            $result = $value;
        };

        Promise\all([])->onResolve($callback);

        self::assertSame([], $result);
    }

    public function testSuccessfulPromisesArray(): void
    {
        $promises = [new Success(1), new Success(2), new Success(3)];

        $callback = function ($exception, $value) use (&$result) {
            $result = $value;
        };

        Promise\all($promises)->onResolve($callback);

        self::assertSame([1, 2, 3], $result);
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

            Promise\all($promises)->onResolve($callback);
        });

        self::assertEquals([1, 2, 3], $result);
    }

    public function testReactPromiseArray(): void
    {
        Loop::run(function () use (&$result) {
            $promises = [
                new Delayed(20, 1),
                new FulfilledPromise(2),
            ];

            $callback = function ($exception, $value) use (&$result) {
                $result = $value;
            };

            Promise\all($promises)->onResolve($callback);
        });

        self::assertEquals([1, 2], $result);
    }

    public function testArrayKeysPreserved(): void
    {
        $expected = ['one' => 1, 'two' => 2, 'three' => 3];

        Loop::run(function () use (&$result) {
            $promises = [
                'one' => new Delayed(20, 1),
                'two' => new Delayed(30, 2),
                'three' => new Delayed(10, 3),
            ];

            $callback = function ($exception, $value) use (&$result) {
                $result = $value;
            };

            Promise\all($promises)->onResolve($callback);
        });

        self::assertEquals($expected, $result);
    }

    public function testNonPromise(): void
    {
        $this->expectException(\TypeError::class);

        Promise\all([1]);
    }
}
