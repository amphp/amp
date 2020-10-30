<?php

namespace Amp\Test;

use Amp\Failure;
use Amp\PHPUnit\AsyncTestCase;
use Amp\PHPUnit\TestException;
use Amp\Promise;
use Amp\Success;
use function Amp\await;
use function Amp\call;

class CallTest extends AsyncTestCase
{
    public function testCallWithFunctionReturningPromise(): void
    {
        $value = 1;
        $promise = call(function ($value) {
            return new Success($value);
        }, $value);

        $this->assertSame($value, await($promise));
    }

    public function testCallWithFunctionReturningValue(): void
    {
        $value = 1;
        $promise = call(function ($value) {
            return $value;
        }, $value);

        $this->assertSame($value, await($promise));
    }

    public function testCallWithThrowingFunction(): void
    {
        $exception = new \Exception;
        $promise = call(function () use ($exception) {
            throw $exception;
        });

        $this->assertInstanceOf(Promise::class, $promise);

        $promise->onResolve(function ($exception, $value) use (&$reason, &$result) {
            $reason = $exception;
            $result = $value;
        });

        try {
            await($promise);
        } catch (\Exception $reason) {
            $this->assertSame($exception, $reason);
            return;
        }

        $this->fail("Returned promise was not failed");
    }

    public function testCallWithGeneratorFunction()
    {
        $value = 1;
        $promise = call(function ($value) {
            return yield new Success($value);
        }, $value);

        $this->assertSame($value, await($promise));
    }

    public function testCallFunctionWithFailure()
    {
        $promise = call(function () {
            return new Failure(new TestException);
        });

        $this->expectException(TestException::class);

        await($promise);
    }
}
