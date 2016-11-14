<?php declare(strict_types = 1);

namespace Amp\Test;

use Amp;
use Amp\{ Failure, Pause, Success };
use Interop\Async\{ Loop, Promise };

class TimeoutTest extends \PHPUnit_Framework_TestCase {
    public function testSuccessfulPromise() {
        Loop::execute(function () {
            $value = 1;

            $promise = new Success($value);

            $promise = Amp\timeout($promise, 100);
            $this->assertInstanceOf(Promise::class, $promise);

            $callback = function ($exception, $value) use (&$result) {
                $result = $value;
            };

            $promise->when($callback);

            $this->assertSame($value, $result);
        });
    }

    public function testFailedPromise() {
        Loop::execute(function () {
            $exception = new \Exception;

            $promise = new Failure($exception);

            $promise = Amp\timeout($promise, 100);
            $this->assertInstanceOf(Promise::class, $promise);

            $callback = function ($exception, $value) use (&$reason) {
                $reason = $exception;
            };

            $promise->when($callback);

            $this->assertSame($exception, $reason);
        });
    }
    
    /**
     * @depends testSuccessfulPromise
     */
    public function testFastPending() {
        $value = 1;

        Loop::execute(function () use (&$result, $value) {
            $promise = new Pause(50, $value);

            $promise = Amp\timeout($promise, 100);
            $this->assertInstanceOf(Promise::class, $promise);

            $callback = function ($exception, $value) use (&$result) {
                $result = $value;
            };

            $promise->when($callback);
        });

        $this->assertSame($value, $result);
    }

    /**
     * @depends testSuccessfulPromise
     */
    public function testSlowPending() {
        Loop::execute(function () use (&$reason) {
            $promise = new Pause(200);

            $promise = Amp\timeout($promise, 100);
            $this->assertInstanceOf(Promise::class, $promise);

            $callback = function ($exception, $value) use (&$reason) {
                $reason = $exception;
            };

            $promise->when($callback);
        });

        $this->assertInstanceOf(Amp\TimeoutException::class, $reason);
    }
}
