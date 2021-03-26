<?php

namespace Amp\Test;

use Amp\Failure;
use Amp\LazyPromise;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Promise;
use Amp\Success;
use function Amp\asyncValue;
use function Amp\await;

class LazyPromiseTest extends AsyncTestCase
{
    public function testPromisorNotCalledOnConstruct(): void
    {
        $invoked = false;
        $lazy = new LazyPromise(function () use (&$invoked) {
            $invoked = true;
        });
        self::assertFalse($invoked);
    }

    public function testPromisorReturningScalar(): void
    {
        $invoked = false;
        $value = 1;
        $lazy = new LazyPromise(function () use (&$invoked, $value): int {
            $invoked = true;
            return $value;
        });

        self::assertSame($value, await($lazy));
        self::assertTrue($invoked);
    }

    public function testPromisorReturningSuccessfulPromise(): void
    {
        $value = 1;
        $promise = new Success($value);
        $lazy = new LazyPromise(static fn (): Promise => $promise);

        self::assertSame($value, await($lazy));
    }

    public function testPromisorReturningFailedPromise(): void
    {
        $exception = new \Exception;
        $promise = new Failure($exception);
        $lazy = new LazyPromise(static fn (): Promise => $promise);

        try {
            await($lazy);
        } catch (\Exception $reason) {
            self::assertSame($exception, $reason);
            return;
        }

        self::fail("Promise was not failed");
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
            self::assertSame($exception, $reason);
            return;
        }

        self::fail("Promise was not failed");
    }

    public function testPromisorCallingAwait(): void
    {
        $value = 1;
        $lazy = new LazyPromise(static fn (): int => await(asyncValue(100, $value)));

        self::assertSame($value, await($lazy));
    }
}
