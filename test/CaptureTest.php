<?php

namespace Amp\Test;

use Amp;
use Amp\Failure;
use Amp\Success;
use Amp\Promise;
use function React\Promise\reject;

class CaptureTest extends \PHPUnit\Framework\TestCase {
    public function testSuccessfulPromise() {
        $invoked = false;
        $callback = function ($exception) use (&$invoked) {
            $invoked = true;
            return -1;
        };

        $value = 1;

        $promise = new Success($value);

        $promise = Amp\capture($promise, \Exception::class, $callback);
        $this->assertInstanceOf(Promise::class, $promise);

        $callback = function ($exception, $value) use (&$result) {
            $result = $value;
        };

        $promise->when($callback);

        $this->assertFalse($invoked);
        $this->assertSame($value, $result);
    }

    public function testFailedPromise() {
        $invoked = false;
        $callback = function ($exception) use (&$invoked, &$reason) {
            $invoked = true;
            $reason = $exception;
            return -1;
        };

        $exception = new \Exception;

        $promise = new Failure($exception);

        $promise = Amp\capture($promise, \Exception::class, $callback);
        $this->assertInstanceOf(Promise::class, $promise);

        $callback = function ($exception, $value) use (&$result) {
            $result = $value;
        };

        $promise->when($callback);

        $this->assertTrue($invoked);
        $this->assertSame($exception, $reason);
        $this->assertSame(-1, $result);
    }

    /**
     * @depends testFailedPromise
     */
    public function testCallbackThrowing() {
        $invoked = false;
        $callback = function ($exception) use (&$invoked) {
            $invoked = true;
            throw new \Exception;
        };

        $exception = new \Exception;

        $promise = new Failure($exception);

        $promise = Amp\capture($promise, \Exception::class, $callback);

        $callback = function ($exception, $value) use (&$reason) {
            $reason = $exception;
        };

        $promise->when($callback);

        $this->assertTrue($invoked);
        $this->assertNotSame($exception, $reason);
    }

    /**
     * @depends testFailedPromise
     */
    public function testUnmatchedExceptionClass() {
        $invoked = false;
        $callback = function ($exception) use (&$invoked, &$reason) {
            $invoked = true;
            $reason = $exception;
            return -1;
        };

        $exception = new \LogicException;

        $promise = new Failure($exception);

        $promise = Amp\capture($promise, \RuntimeException::class, $callback);

        $callback = function ($exception, $value) use (&$reason) {
            $reason = $exception;
        };

        $promise->when($callback);

        $this->assertFalse($invoked);
        $this->assertSame($exception, $reason);
    }

    /**
     * @depends testFailedPromise
     */
    public function testReactPromise() {
        $invoked = false;
        $callback = function ($exception) use (&$invoked, &$reason) {
            $invoked = true;
            $reason = $exception;
            return -1;
        };

        $exception = new \Exception;

        $promise = reject($exception);

        $promise = Amp\capture($promise, \Exception::class, $callback);
        $this->assertInstanceOf(Promise::class, $promise);

        $callback = function ($exception, $value) use (&$result) {
            $result = $value;
        };

        $promise->when($callback);

        $this->assertTrue($invoked);
        $this->assertSame($exception, $reason);
        $this->assertSame(-1, $result);
    }

    public function testNonPromise() {
        $this->expectException(Amp\UnionTypeError::class);
        Amp\capture(42, \Error::class, function () {});
    }
}
