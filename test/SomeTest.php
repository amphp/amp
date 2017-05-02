<?php

namespace Amp\Test;

use Amp\Delayed;
use Amp\Failure;
use Amp\Loop;
use Amp\MultiReasonException;
use Amp\Promise;
use Amp\Success;
use PHPUnit\Framework\TestCase;
use React\Promise\FulfilledPromise;

class SomeTest extends TestCase {
    public function testEmptyArray() {
        $this->assertSame([[], []], Promise\wait(Promise\some([], 0)));
    }

    /**
     * @expectedException \Error
     * @expectedExceptionMessage Too few promises provided
     */
    public function testEmptyArrayWithNonZeroRequired() {
        Promise\some([], 1);
    }

    /**
     * @expectedException \Error
     * @expectedExceptionMessage non-negative
     */
    public function testInvalidRequiredNumberOfPromises() {
        Promise\some([], -1);
    }

    public function testSuccessfulPromisesArray() {
        $promises = [new Success(1), new Success(2), new Success(3)];

        $callback = function ($exception, $value) use (&$result) {
            $result = $value;
        };

        Promise\some($promises)->onResolve($callback);

        $this->assertSame([[], [1, 2, 3]], $result);
    }

    public function testReactPromiseArray() {
        $promises = [new FulfilledPromise(1), new FulfilledPromise(2), new Success(3)];

        $callback = function ($exception, $value) use (&$result) {
            $result = $value;
        };

        Promise\some($promises)->onResolve($callback);

        $this->assertSame([[], [1, 2, 3]], $result);
    }

    public function testFailedPromisesArray() {
        $exception = new \Exception;
        $promises = [new Failure($exception), new Failure($exception), new Failure($exception)];

        $callback = function ($exception, $value) use (&$reason) {
            $reason = $exception;
        };

        Promise\some($promises)->onResolve($callback);

        $this->assertInstanceOf(MultiReasonException::class, $reason);
        $this->assertSame([$exception, $exception, $exception], $reason->getReasons());
    }

    public function testSuccessfulAndFailedPromisesArray() {
        $exception = new \Exception;
        $promises = [new Failure($exception), new Failure($exception), new Success(3)];

        $callback = function ($exception, $value) use (&$result) {
            $result = $value;
        };

        Promise\some($promises)->onResolve($callback);

        $this->assertSame([[0 => $exception, 1 => $exception], [2 => 3]], $result);
    }

    public function testPendingAwatiablesArray() {
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

    public function testArrayKeysPreserved() {
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

    /**
     * @expectedException \Error
     */
    public function testNonPromise() {
        Promise\some([1]);
    }
}
