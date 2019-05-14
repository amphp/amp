<?php

namespace Amp\Test;

use Amp\Delayed;
use Amp\Failure;
use Amp\Loop;
use Amp\Promise;
use Amp\Success;

class AnyTest extends BaseTest
{
    public function testEmptyArray()
    {
        $callback = function ($exception, $value) use (&$result) {
            $result = $value;
        };

        Promise\any([])->onResolve($callback);

        $this->assertSame([[], []], $result);
    }

    public function testSuccessfulPromisesArray()
    {
        $promises = [new Success(1), new Success(2), new Success(3)];

        $callback = function ($exception, $value) use (&$result) {
            $result = $value;
        };

        Promise\any($promises)->onResolve($callback);

        $this->assertSame([[], [1, 2, 3]], $result);
    }

    public function testFailedPromisesArray()
    {
        $exception = new \Exception;
        $promises = [new Failure($exception), new Failure($exception), new Failure($exception)];

        $callback = function ($exception, $value) use (&$result) {
            $result = $value;
        };

        Promise\any($promises)->onResolve($callback);

        $this->assertSame([[$exception, $exception, $exception], []], $result);
    }

    public function testMixedPromisesArray()
    {
        $exception = new \Exception;
        $promises = [new Success(1), new Failure($exception), new Success(3)];

        $callback = function ($exception, $value) use (&$result) {
            $result = $value;
        };

        Promise\any($promises)->onResolve($callback);

        $this->assertSame([[1 => $exception], [0 => 1, 2 => 3]], $result);
    }

    public function testPendingPromiseArray()
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

        $this->assertEquals([[], [1, 2, 3]], $result);
    }

    /**
     * @depends testMixedPromisesArray
     */
    public function testArrayKeysPreserved()
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

        $this->assertEquals($expected, $result);
    }

    /**
     * @expectedException \TypeError
     */
    public function testNonPromise()
    {
        Promise\any([1]);
    }
}
