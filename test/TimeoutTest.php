<?php declare(strict_types = 1);

namespace Amp\Test;

use Amp;
use Amp\Failure;
use Amp\Pause;
use Amp\Success;
use Interop\Async\Awaitable;
use Interop\Async\Loop;

class TimeoutTest extends \PHPUnit_Framework_TestCase {
    public function testSuccessfulAwaitable() {
        Loop::execute(function () {
            $value = 1;

            $awaitable = new Success($value);

            $awaitable = Amp\timeout($awaitable, 100);
            $this->assertInstanceOf(Awaitable::class, $awaitable);

            $callback = function ($exception, $value) use (&$result) {
                $result = $value;
            };

            $awaitable->when($callback);

            $this->assertSame($value, $result);
        });
    }

    public function testFailedAwaitable() {
        Loop::execute(function () {
            $exception = new \Exception;

            $awaitable = new Failure($exception);

            $awaitable = Amp\timeout($awaitable, 100);
            $this->assertInstanceOf(Awaitable::class, $awaitable);

            $callback = function ($exception, $value) use (&$reason) {
                $reason = $exception;
            };

            $awaitable->when($callback);

            $this->assertSame($exception, $reason);
        });
    }
    
    /**
     * @depends testSuccessfulAwaitable
     */
    public function testFastPending() {
        $value = 1;

        Loop::execute(function () use (&$result, $value) {
            $awaitable = new Pause(50, $value);

            $awaitable = Amp\timeout($awaitable, 100);
            $this->assertInstanceOf(Awaitable::class, $awaitable);

            $callback = function ($exception, $value) use (&$result) {
                $result = $value;
            };

            $awaitable->when($callback);
        });

        $this->assertSame($value, $result);
    }

    /**
     * @depends testSuccessfulAwaitable
     */
    public function testSlowPending() {
        Loop::execute(function () use (&$reason) {
            $awaitable = new Pause(200);

            $awaitable = Amp\timeout($awaitable, 100);
            $this->assertInstanceOf(Awaitable::class, $awaitable);

            $callback = function ($exception, $value) use (&$reason) {
                $reason = $exception;
            };

            $awaitable->when($callback);
        });

        $this->assertInstanceOf(Amp\TimeoutException::class, $reason);
    }
}
