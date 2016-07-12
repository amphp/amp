<?php

namespace Amp\Test;

use Amp;
use Amp\Failure;
use Amp\MultiReasonException;
use Amp\Pause;
use Amp\Success;
use Interop\Async\Loop;

class SomeTest extends \PHPUnit_Framework_TestCase {
    /**
     * @expectedException \InvalidArgumentException
     */
    public function testEmptyArray() {
        Amp\some([], 1);
    }

    public function testSuccessfulAwaitablesArray() {
        $awaitables = [new Success(1), new Success(2), new Success(3)];

        $callback = function ($exception, $value) use (&$result) {
            $result = $value;
        };

        Amp\some($awaitables, 2)->when($callback);

        $this->assertSame([1, 2], $result);
    }

    public function testSuccessfulAndFailedAwaitablesArray() {
        $awaitables = [new Failure(new \Exception), new Failure(new \Exception), new Success(3)];

        $callback = function ($exception, $value) use (&$result) {
            $result = $value;
        };

        Amp\some($awaitables, 1)->when($callback);

        $this->assertSame([2 => 3], $result);
    }

    public function testTooManyFailedAwaitables() {
        $awaitables = [new Failure(new \Exception), new Failure(new \Exception), new Success(3)];

        $callback = function ($exception, $value) use (&$reason) {
            $reason = $exception;
        };

        Amp\some($awaitables, 2)->when($callback);

        $this->assertInstanceOf(MultiReasonException::class, $reason);

        $reasons = $reason->getReasons();

        foreach ($reasons as $reason) {
            $this->assertInstanceOf(\Exception::class, $reason);
        }
    }

    public function testPendingAwatiablesArray() {
        Loop::execute(function () use (&$result) {
            $awaitables = [
                new Pause(0.2, 1),
                new Pause(0.3, 2),
                new Pause(0.1, 3),
            ];

            $callback = function ($exception, $value) use (&$result) {
                $result = $value;
            };

            Amp\some($awaitables, 2)->when($callback);
        });

        $this->assertEquals([0 => 1, 2 => 3], $result);
    }

    public function testArrayKeysPreserved() {
        $expected = ['one' => 1, 'two' => 2, 'three' => 3];

        Loop::execute(function () use (&$result) {
            $awaitables = [
                'one'   => new Pause(0.2, 1),
                'two'   => new Pause(0.3, 2),
                'three' => new Pause(0.1, 3),
            ];

            $callback = function ($exception, $value) use (&$result) {
                $result = $value;
            };

            Amp\some($awaitables, 3)->when($callback);
        });

        $this->assertEquals($expected, $result);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testNonAwaitable() {
        Amp\some([1], 1);
    }
}
