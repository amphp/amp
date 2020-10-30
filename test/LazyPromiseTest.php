<?php

namespace Amp\Test;

use Amp\Delayed;
use Amp\Failure;
use Amp\LazyPromise;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Promise;
use Amp\Success;
use function Amp\await;

class LazyPromiseTest extends AsyncTestCase
{
    public function testPromisorNotCalledOnConstruct(): void
    {
        $invoked = false;
        $lazy = new LazyPromise(function () use (&$invoked) {
            $invoked = true;
        });
        $this->assertFalse($invoked);
    }

    public function testPromisorReturningScalar(): void
    {
        $invoked = false;
        $value = 1;
        $lazy = new LazyPromise(function () use (&$invoked, $value): int {
            $invoked = true;
            return $value;
        });

        $this->assertSame($value, await($lazy));
        $this->assertTrue($invoked);
    }

    public function testPromisorReturningSuccessfulPromise(): void
    {
        $value = 1;
        $promise = new Success($value);
        $lazy = new LazyPromise(static fn (): Promise => $promise);

        $this->assertSame($value, await($lazy));
    }

    public function testPromisorReturningFailedPromise(): void
    {
        $exception = new \Exception;
        $promise = new Failure($exception);
        $lazy = new LazyPromise(static fn (): Promise => $promise);

        try {
            await($lazy);
        } catch (\Exception $reason) {
            $this->assertSame($exception, $reason);
            return;
        }

        $this->fail("Promise was not failed");
    }

    public function testPromisorThrowingException(): void
    {
        $exception = new \Exception;
        $lazy = new LazyPromise(function () use ($exception): void {
            throw $exception;
        });

        try {
            await($lazy);
        } catch (\Exception $reason) {
            $this->assertSame($exception, $reason);
            return;
        }

        $this->fail("Promise was not failed");
    }

    public function testPromisorCallingAwait(): void
    {
        $value = 1;
        $lazy = new LazyPromise(static fn (): int => await(new Delayed(100, $value)));

        $this->assertSame($value, await($lazy));
    }
}
