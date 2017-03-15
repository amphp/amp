<?php

namespace Amp\Test;

use Amp\Failure;
use Amp\MultiReasonException;
use Amp\Pause;
use Amp\Promise;
use Amp\Success;
use Amp\Loop;

class SomeTest extends \PHPUnit\Framework\TestCase {
    public function testEmptyArray() {
        $this->assertSame([[], []], Promise\wait(Promise\some([])));
    }

    public function testSuccessfulPromisesArray() {
        $promises = [new Success(1), new Success(2), new Success(3)];

        $callback = function ($exception, $value) use (&$result) {
            $result = $value;
        };

        Promise\some($promises)->when($callback);

        $this->assertSame([[], [1, 2, 3]], $result);
    }

    public function testFailedPromisesArray() {
        $exception = new \Exception;
        $promises = [new Failure($exception), new Failure($exception), new Failure($exception)];

        $callback = function ($exception, $value) use (&$reason) {
            $reason = $exception;
        };

        Promise\some($promises)->when($callback);

        $this->assertInstanceOf(MultiReasonException::class, $reason);
        $this->assertEquals([$exception, $exception, $exception], $reason->getReasons());
    }

    public function testSuccessfulAndFailedPromisesArray() {
        $exception = new \Exception;
        $promises = [new Failure($exception), new Failure($exception), new Success(3)];

        $callback = function ($exception, $value) use (&$result) {
            $result = $value;
        };

        Promise\some($promises)->when($callback);

        $this->assertSame([[0 => $exception, 1 => $exception], [2 => 3]], $result);
    }

    public function testPendingAwatiablesArray() {
        Loop::run(function () use (&$result) {
            $promises = [
                new Pause(20, 1),
                new Pause(30, 2),
                new Pause(10, 3),
            ];

            $callback = function ($exception, $value) use (&$result) {
                $result = $value;
            };

            Promise\some($promises)->when($callback);
        });

        $this->assertEquals([[], [0 => 1, 1 => 2, 2 => 3]], $result);
    }

    public function testArrayKeysPreserved() {
        $expected = [[], ['one' => 1, 'two' => 2, 'three' => 3]];

        Loop::run(function () use (&$result) {
            $promises = [
                'one'   => new Pause(20, 1),
                'two'   => new Pause(30, 2),
                'three' => new Pause(10, 3),
            ];

            $callback = function ($exception, $value) use (&$result) {
                $result = $value;
            };

            Promise\some($promises)->when($callback);
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
