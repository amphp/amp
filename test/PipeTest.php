<?php

namespace Amp\Test;

use Amp;
use Amp\Failure;
use Amp\Success;
use Amp\Promise;
use function React\Promise\resolve;

class PipeTest extends \PHPUnit\Framework\TestCase {
    public function testSuccessfulPromise() {
        $invoked = false;
        $callback = function ($value) use (&$invoked) {
            $invoked = true;
            return $value + 1;
        };

        $value = 1;

        $promise = new Success($value);

        $promise = Amp\pipe($promise, $callback);
        $this->assertInstanceOf(Promise::class, $promise);

        $callback = function ($exception, $value) use (&$result) {
            $result = $value;
        };

        $promise->when($callback);

        $this->assertTrue($invoked);
        $this->assertSame($value + 1, $result);
    }

    public function testFailedPromise() {
        $invoked = false;
        $callback = function ($value) use (&$invoked) {
            $invoked = true;
            return $value + 1;
        };

        $exception = new \Exception;

        $promise = new Failure($exception);

        $promise = Amp\pipe($promise, $callback);
        $this->assertInstanceOf(Promise::class, $promise);

        $callback = function ($exception, $value) use (&$reason) {
            $reason = $exception;
        };

        $promise->when($callback);

        $this->assertFalse($invoked);
        $this->assertSame($exception, $reason);
    }

    /**
     * @depends testSuccessfulPromise
     */
    public function testCallbackThrowing() {
        $exception = new \Exception;
        $callback = function ($value) use (&$invoked, $exception) {
            $invoked = true;
            throw $exception;
        };

        $value = 1;

        $promise = new Success($value);

        $promise = Amp\pipe($promise, $callback);

        $callback = function ($exception, $value) use (&$reason) {
            $reason = $exception;
        };

        $promise->when($callback);

        $this->assertTrue($invoked);
        $this->assertSame($exception, $reason);
    }

    /**
     * @depends testSuccessfulPromise
     */
    public function testReactPromise() {
        $invoked = false;
        $callback = function ($value) use (&$invoked) {
            $invoked = true;
            return $value + 1;
        };

        $value = 1;

        $promise = resolve($value);

        $promise = Amp\pipe($promise, $callback);
        $this->assertInstanceOf(Promise::class, $promise);

        $callback = function ($exception, $value) use (&$result) {
            $result = $value;
        };

        $promise->when($callback);

        $this->assertTrue($invoked);
        $this->assertSame($value + 1, $result);
    }

    public function testNonPromise() {
        $this->expectException(Amp\UnionTypeError::class);
        Amp\pipe(42, function () {});
    }
}
