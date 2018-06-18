<?php

namespace Amp\Test;

use Amp;
use Amp\Coroutine;
use Amp\Failure;
use Amp\Loop;
use Amp\PHPUnit\TestException;
use Amp\Promise;
use Amp\Success;
use PHPUnit\Framework\TestCase;
use React\Promise\FulfilledPromise as FulfilledReactPromise;

class CallTest extends TestCase
{
    public function testCallWithFunctionReturningPromise()
    {
        $value = 1;
        $promise = Amp\call(function ($value) {
            return new Success($value);
        }, $value);

        $this->assertInstanceOf(Promise::class, $promise);

        $promise->onResolve(function ($exception, $value) use (&$reason, &$result) {
            $reason = $exception;
            $result = $value;
        });

        $this->assertNull($reason);
        $this->assertSame($value, $result);
    }

    public function testCallWithFunctionReturningValue()
    {
        $value = 1;
        $promise = Amp\call(function ($value) {
            return $value;
        }, $value);

        $this->assertInstanceOf(Promise::class, $promise);

        $promise->onResolve(function ($exception, $value) use (&$reason, &$result) {
            $reason = $exception;
            $result = $value;
        });

        $this->assertNull($reason);
        $this->assertSame($value, $result);
    }

    public function testCallWithThrowingFunction()
    {
        $exception = new \Exception;
        $promise = Amp\call(function () use ($exception) {
            throw $exception;
        });

        $this->assertInstanceOf(Promise::class, $promise);

        $promise->onResolve(function ($exception, $value) use (&$reason, &$result) {
            $reason = $exception;
            $result = $value;
        });

        $this->assertSame($exception, $reason);
        $this->assertNull($result);
    }

    public function testCallWithFunctionReturningReactPromise()
    {
        $value = 1;
        $promise = Amp\call(function ($value) {
            return new FulfilledReactPromise($value);
        }, $value);

        $this->assertInstanceOf(Promise::class, $promise);

        $promise->onResolve(function ($exception, $value) use (&$reason, &$result) {
            $reason = $exception;
            $result = $value;
        });

        $this->assertNull($reason);
        $this->assertSame($value, $result);
    }

    public function testCallWithGeneratorFunction()
    {
        $value = 1;
        $promise = Amp\call(function ($value) {
            return yield new Success($value);
        }, $value);

        $this->assertInstanceOf(Coroutine::class, $promise);

        $promise->onResolve(function ($exception, $value) use (&$reason, &$result) {
            $reason = $exception;
            $result = $value;
        });

        $this->assertNull($reason);
        $this->assertSame($value, $result);
    }

    public function testAsyncCallFunctionWithFailure()
    {
        \Amp\asyncCall(function ($value) {
            return new Failure(new TestException);
        }, 42);

        $this->expectException(TestException::class);

        Loop::run();
    }
}
