<?php

namespace Amp\Test;

use Amp;
use Amp\Failure;
use Amp\LazyPromise;
use Amp\Success;
use PHPUnit\Framework\TestCase;
use React\Promise\FulfilledPromise as FulfilledReactPromise;
use React\Promise\RejectedPromise as RejectedReactPromise;

class LazyPromiseTest extends TestCase {
    public function testPromisorNotCalledOnConstruct() {
        $invoked = false;
        $lazy = new LazyPromise(function () use (&$invoked) {
            $invoked = true;
        });
        $this->assertFalse($invoked);
    }

    public function testPromisorReturningScalar() {
        $invoked = false;
        $value = 1;
        $lazy = new LazyPromise(function () use (&$invoked, $value) {
            $invoked = true;
            return $value;
        });

        $lazy->onResolve(function ($exception, $value) use (&$result) {
            $result = $value;
        });

        $this->assertTrue($invoked);
        $this->assertSame($value, $result);
    }

    public function testPromisorReturningSuccessfulPromise() {
        $invoked = false;
        $value = 1;
        $promise = new Success($value);
        $lazy = new LazyPromise(function () use (&$invoked, $promise) {
            $invoked = true;
            return $promise;
        });

        $lazy->onResolve(function ($exception, $value) use (&$result) {
            $result = $value;
        });

        $this->assertTrue($invoked);
        $this->assertSame($value, $result);
    }

    public function testPromisorReturningFailedPromise() {
        $invoked = false;
        $exception = new \Exception;
        $promise = new Failure($exception);
        $lazy = new LazyPromise(function () use (&$invoked, $promise) {
            $invoked = true;
            return $promise;
        });

        $lazy->onResolve(function ($exception, $value) use (&$reason) {
            $reason = $exception;
        });

        $this->assertTrue($invoked);
        $this->assertSame($exception, $reason);
    }

    public function testPromisorThrowingException() {
        $invoked = false;
        $exception = new \Exception;
        $lazy = new LazyPromise(function () use (&$invoked, $exception) {
            $invoked = true;
            throw $exception;
        });

        $lazy->onResolve(function ($exception, $value) use (&$reason) {
            $reason = $exception;
        });

        $this->assertTrue($invoked);
        $this->assertSame($exception, $reason);
    }

    public function testPromisorReturningSuccessfulReactPromise() {
        $invoked = false;
        $value = 1;
        $promise = new FulfilledReactPromise($value);
        $lazy = new LazyPromise(function () use (&$invoked, $promise) {
            $invoked = true;
            return $promise;
        });

        $lazy->onResolve(function ($exception, $value) use (&$result) {
            $result = $value;
        });

        $this->assertTrue($invoked);
        $this->assertSame($value, $result);
    }

    public function testPromisorReturningFailedReactPromise() {
        $invoked = false;
        $exception = new \Exception;
        $promise = new RejectedReactPromise($exception);
        $lazy = new LazyPromise(function () use (&$invoked, $promise) {
            $invoked = true;
            return $promise;
        });

        $lazy->onResolve(function ($exception, $value) use (&$reason) {
            $reason = $exception;
        });

        $this->assertTrue($invoked);
        $this->assertSame($exception, $reason);
    }

    public function testPromisorReturningGenerator() {
        $invoked = false;
        $value = 1;
        $lazy = new LazyPromise(function () use (&$invoked, $value) {
            $invoked = true;
            return $value;
            yield; // Unreachable, but makes function a generator.
        });

        $lazy->onResolve(function ($exception, $value) use (&$result) {
            $result = $value;
        });

        $this->assertTrue($invoked);
        $this->assertSame($value, $result);
    }
}
