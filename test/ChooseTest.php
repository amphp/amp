<?php

namespace Amp\Test;

use Amp;
use Amp\{ Failure, Pause, Success };
use Interop\Async\Loop;

class ChooseTest extends \PHPUnit_Framework_TestCase {
    /**
     * @expectedException \Error
     * @expectedExceptionMessage No promises provided
     */
    public function testEmptyArray() {
        Amp\choose([]);
    }

    public function testSuccessfulPromisesArray() {
        $promises = [new Success(1), new Success(2), new Success(3)];

        $callback = function ($exception, $value) use (&$result) {
            $result = $value;
        };

        Amp\choose($promises)->when($callback);

        $this->assertSame(1, $result);
    }
    
    public function testFailedPromisesArray() {
        $exception = new \Exception;
        $promises = [new Failure($exception), new Failure($exception), new Failure($exception)];
    
        $callback = function ($exception, $value) use (&$reason) {
            $reason = $exception;
        };
    
        Amp\choose($promises)->when($callback);
    
        $this->assertSame($exception, $reason);
    }
    
    public function testFirstPromiseSuccessfulArray() {
        $exception = new \Exception;
        $promises = [new Success(1), new Failure($exception), new Success(3)];
    
        $callback = function ($exception, $value) use (&$result) {
            $result = $value;
        };
    
        Amp\choose($promises)->when($callback);
    
        $this->assertSame(1, $result);
    }
    
    public function testFirstPromiseFailedArray() {
        $exception = new \Exception;
        $promises = [new Failure($exception), new Success(2), new Success(3)];
    
        $callback = function ($exception, $value) use (&$reason) {
            $reason = $exception;
        };
    
        Amp\choose($promises)->when($callback);
    
        $this->assertSame($exception, $reason);
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

            Amp\choose($promises)->when($callback);
        });
    
        $this->assertSame(3, $result);
    }
    
    /**
     * @expectedException \Error
     * @expectedExceptionMessage Non-promise provided
     */
    public function testNonPromise() {
        Amp\choose([1]);
    }
}
