<?php

namespace Amp\Test;

use Amp;
use Amp\Pause;
use Amp\Success;
use Interop\Async\Loop;

class AllTest extends \PHPUnit_Framework_TestCase {
    public function testEmptyArray() {
        $callback = function ($exception, $value) use (&$result) {
            $result = $value;
        };

        Amp\all([])->when($callback);

        $this->assertSame([], $result);
    }

    public function testSuccessfulAwaitablesArray() {
        $awaitables = [new Success(1), new Success(2), new Success(3)];

        $callback = function ($exception, $value) use (&$result) {
            $result = $value;
        };

        Amp\all($awaitables)->when($callback);

        $this->assertSame([1, 2, 3], $result);
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

            Amp\all($awaitables)->when($callback);
        });

        $this->assertEquals([1, 2, 3], $result);
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

            Amp\all($awaitables)->when($callback);
        });

        $this->assertEquals($expected, $result);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testNonAwaitable() {
        Amp\all([1]);
    }
}
