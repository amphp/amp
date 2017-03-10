<?php

namespace Amp\Test;

use Amp;
use Amp\Failure;
use Amp\MultiReasonException;
use Amp\Pause;
use Amp\Success;
use Amp\Loop;

class FirstTest extends \PHPUnit_Framework_TestCase {
    /**
     * @expectedException \Error
     * @expectedExceptionMessage No promises provided
     */
    public function testEmptyArray() {
        Amp\first([]);
    }

    public function testSuccessfulPromisesArray() {
        $promises = [new Success(1), new Success(2), new Success(3)];

        $callback = function ($exception, $value) use (&$result) {
            $result = $value;
        };

        Amp\first($promises)->when($callback);

        $this->assertSame(1, $result);
    }

    public function testFailedPromisesArray() {
        $exception = new \Exception;
        $promises = [new Failure($exception), new Failure($exception), new Failure($exception)];

        $callback = function ($exception, $value) use (&$reason) {
            $reason = $exception;
        };

        Amp\first($promises)->when($callback);

        $this->assertInstanceOf(MultiReasonException::class, $reason);
        $this->assertEquals([$exception, $exception, $exception], $reason->getReasons());
    }

    public function testMixedPromisesArray() {
        $exception = new \Exception;
        $promises = [new Failure($exception), new Failure($exception), new Success(3)];

        $callback = function ($exception, $value) use (&$result) {
            $result = $value;
        };

        Amp\first($promises)->when($callback);

        $this->assertSame(3, $result);
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

            Amp\first($promises)->when($callback);
        });

        $this->assertSame(3, $result);
    }

    /**
     * @expectedException \Error
     * @expectedExceptionMessage Non-promise provided
     */
    public function testNonPromise() {
        Amp\first([1]);
    }
}
