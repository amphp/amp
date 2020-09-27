<?php

namespace Amp\Test;

use Amp\Delayed;
use Amp\Failure;
use Amp\LazyPromise;
use Amp\Promise;
use Amp\Success;
use React\Promise\PromiseInterface as ReactPromise;
use function Amp\await;
use function React\Promise\reject;
use function React\Promise\resolve;

class LazyPromiseTest extends BaseTest
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

    public function testPromisorReturningSuccessfulReactPromise(): void
    {
        $value = 1;
        $promise = resolve($value);
        $lazy = new LazyPromise(static fn (): ReactPromise => $promise);

        $this->assertSame($value, await($lazy));
    }

    public function testPromisorReturningFailedReactPromise(): void
    {
        $exception = new \Exception;
        $promise = reject($exception);
        $lazy = new LazyPromise(static fn (): ReactPromise => $promise);

        try {
            await($lazy);
        } catch (\Exception $reason) {
            $this->assertSame($exception, $reason);
            return;
        }

        $this->fail("Promise was not failed");
    }

    public function testPromisorReturningGenerator(): void
    {
        $value = 1;
        $lazy = new LazyPromise(function () use ($value): \Generator {
            return $value;
            yield; // Unreachable, but makes function a generator.
        });

        $this->assertSame($value, await($lazy));
    }

    public function testPromisorCallingAwait(): void
    {
        $value = 1;
        $lazy = new LazyPromise(static fn (): int => await(new Delayed(100, $value)));

        $this->assertSame($value, await($lazy));
    }
}
