<?php

namespace Amp\Test;

use Amp;
use Amp\Failure;
use Amp\Success;
use Interop\Async\Awaitable;

class CaptureTest extends \PHPUnit_Framework_TestCase {
    public function testSuccessfulAwaitable() {
        $invoked = false;
        $callback = function ($exception) use (&$invoked) {
            $invoked = true;
            return -1;
        };

        $value = 1;

        $awaitable = new Success($value);

        $awaitable = Amp\capture($awaitable, \Exception::class, $callback);
        $this->assertInstanceOf(Awaitable::class, $awaitable);

        $callback = function ($exception, $value) use (&$result) {
            $result = $value;
        };

        $awaitable->when($callback);

        $this->assertFalse($invoked);
        $this->assertSame($value, $result);
    }

    public function testFailedAwaitable() {
        $invoked = false;
        $callback = function ($exception) use (&$invoked, &$reason) {
            $invoked = true;
            $reason = $exception;
            return -1;
        };

        $exception = new \Exception;

        $awaitable = new Failure($exception);

        $awaitable = Amp\capture($awaitable, \Exception::class, $callback);
        $this->assertInstanceOf(Awaitable::class, $awaitable);

        $callback = function ($exception, $value) use (&$result) {
            $result = $value;
        };

        $awaitable->when($callback);

        $this->assertTrue($invoked);
        $this->assertSame($exception, $reason);
        $this->assertSame(-1, $result);
    }
    
    /**
     * @depends testFailedAwaitable
     */
    public function testCallbackThrowing() {
        $invoked = false;
        $callback = function ($exception) use (&$invoked) {
            $invoked = true;
            throw new \Exception;
        };

        $exception = new \Exception;

        $awaitable = new Failure($exception);

        $awaitable = Amp\capture($awaitable, \Exception::class, $callback);

        $callback = function ($exception, $value) use (&$reason) {
            $reason = $exception;
        };

        $awaitable->when($callback);

        $this->assertTrue($invoked);
        $this->assertNotSame($exception, $reason);
    }

    /**
     * @depends testFailedAwaitable
     */
    public function testUnmatchedExceptionClass() {
        $invoked = false;
        $callback = function ($exception) use (&$invoked, &$reason) {
            $invoked = true;
            $reason = $exception;
            return -1;
        };

        $exception = new \LogicException;

        $awaitable = new Failure($exception);

        $awaitable = Amp\capture($awaitable, \RuntimeException::class, $callback);

        $callback = function ($exception, $value) use (&$reason) {
            $reason = $exception;
        };

        $awaitable->when($callback);

        $this->assertFalse($invoked);
        $this->assertSame($exception, $reason);
    }
}
