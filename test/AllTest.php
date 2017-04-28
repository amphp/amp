<?php

namespace Amp\Test;

use Amp\Delayed;
use Amp\Loop;
use Amp\Promise;
use Amp\Success;

class AllTest extends \PHPUnit\Framework\TestCase {
    public function testEmptyArray() {
        $callback = function ($exception, $value) use (&$result) {
            $result = $value;
        };

        Promise\all([])->onResolve($callback);

        $this->assertSame([], $result);
    }

    public function testSuccessfulPromisesArray() {
        $promises = [new Success(1), new Success(2), new Success(3)];

        $callback = function ($exception, $value) use (&$result) {
            $result = $value;
        };

        Promise\all($promises)->onResolve($callback);

        $this->assertSame([1, 2, 3], $result);
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

            Promise\all($promises)->onResolve($callback);
        });

        $this->assertEquals([1, 2, 3], $result);
    }

    public function testArrayKeysPreserved() {
        $expected = ['one' => 1, 'two' => 2, 'three' => 3];

        Loop::run(function () use (&$result) {
            $promises = [
                'one'   => new Delayed(20, 1),
                'two'   => new Delayed(30, 2),
                'three' => new Delayed(10, 3),
            ];

            $callback = function ($exception, $value) use (&$result) {
                $result = $value;
            };

            Promise\all($promises)->onResolve($callback);
        });

        $this->assertEquals($expected, $result);
    }

    /**
     * @expectedException \TypeError
     */
    public function testNonPromise() {
        Promise\all([1]);
    }
}
