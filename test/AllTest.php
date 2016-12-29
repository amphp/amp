<?php

namespace Amp\Test;

use Amp;
use Amp\{ Pause, Success };
use Interop\Async\Loop;

class AllTest extends \PHPUnit_Framework_TestCase {
    public function testEmptyArray() {
        $callback = function ($exception, $value) use (&$result) {
            $result = $value;
        };

        Amp\all([])->when($callback);

        $this->assertSame([], $result);
    }

    public function testSuccessfulPromisesArray() {
        $promises = [new Success(1), new Success(2), new Success(3)];

        $callback = function ($exception, $value) use (&$result) {
            $result = $value;
        };

        Amp\all($promises)->when($callback);

        $this->assertSame([1, 2, 3], $result);
    }

    public function testPendingAwatiablesArray() {
        Loop::execute(function () use (&$result) {
            $promises = [
                new Pause(20, 1),
                new Pause(30, 2),
                new Pause(10, 3),
            ];

            $callback = function ($exception, $value) use (&$result) {
                $result = $value;
            };

            Amp\all($promises)->when($callback);
        });

        $this->assertEquals([1, 2, 3], $result);
    }

    public function testArrayKeysPreserved() {
        $expected = ['one' => 1, 'two' => 2, 'three' => 3];

        Loop::execute(function () use (&$result) {
            $promises = [
                'one'   => new Pause(20, 1),
                'two'   => new Pause(30, 2),
                'three' => new Pause(10, 3),
            ];

            $callback = function ($exception, $value) use (&$result) {
                $result = $value;
            };

            Amp\all($promises)->when($callback);
        });

        $this->assertEquals($expected, $result);
    }

    /**
     * @expectedException \Error
     * @expectedExceptionMessage Non-promise provided
     */
    public function testNonPromise() {
        Amp\all([1]);
    }
}
