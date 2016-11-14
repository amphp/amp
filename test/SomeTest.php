<?php declare(strict_types = 1);

namespace Amp\Test;

use Amp;
use Amp\{ Failure, Pause, Success };
use Interop\Async\Loop;

class SomeTest extends \PHPUnit_Framework_TestCase {
    /**
     * @expectedException \Error
     */
    public function testEmptyArray() {
        Amp\some([]);
    }

    public function testSuccessfulPromisesArray() {
        $promises = [new Success(1), new Success(2), new Success(3)];

        $callback = function ($exception, $value) use (&$result) {
            $result = $value;
        };

        Amp\some($promises)->when($callback);

        $this->assertSame([[], [1, 2, 3]], $result);
    }

    public function testSuccessfulAndFailedPromisesArray() {
        $exception = new \Exception;
        $promises = [new Failure($exception), new Failure($exception), new Success(3)];

        $callback = function ($exception, $value) use (&$result) {
            $result = $value;
        };

        Amp\some($promises)->when($callback);

        $this->assertSame([[0 => $exception, 1 => $exception], [2 => 3]], $result);
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

            Amp\some($promises)->when($callback);
        });

        $this->assertEquals([[], [0 => 1, 1 => 2, 2 => 3]], $result);
    }

    public function testArrayKeysPreserved() {
        $expected = [[], ['one' => 1, 'two' => 2, 'three' => 3]];

        Loop::execute(function () use (&$result) {
            $promises = [
                'one'   => new Pause(20, 1),
                'two'   => new Pause(30, 2),
                'three' => new Pause(10, 3),
            ];

            $callback = function ($exception, $value) use (&$result) {
                $result = $value;
            };

            Amp\some($promises)->when($callback);
        });

        $this->assertEquals($expected, $result);
    }

    /**
     * @expectedException \Error
     */
    public function testNonPromise() {
        Amp\some([1]);
    }
}
