<?php

namespace Amp;

use Amp\PHPUnit\AsyncTestCase;
use Amp\PHPUnit\TestException;
use Revolt\EventLoop;

class FutureTest extends AsyncTestCase
{
    public function testApplyWithCompleteFuture(): void
    {
        $future = Future::resolve(1);
        $future = $future->apply(static fn (int $value) => $value + 1);
        self::assertSame(2, $future->await());
    }

    public function testApplyWithSuspendInCallback(): void
    {
        $future = Future::resolve(1);
        $future = $future->apply(static fn (int $value) => $value + Future::resolve(1)->await());
        self::assertSame(2, $future->await());
    }
    public function testApplyWithPendingFuture(): void
    {
        $deferred = new Deferred;

        $future = $deferred->getFuture();
        $future = $future->apply(static fn (int $value) => $value + 1);

        EventLoop::delay(0.1, static fn () => $deferred->resolve(1));

        self::assertSame(2, $future->await());
    }

    public function testApplyWithErrorFuture(): void
    {
        $future = Future::error($exception = new TestException());
        $future = $future->apply($this->createCallback(0));
        $this->expectExceptionObject($exception);
        $future->await();
    }

    public function testApplyWithThrowingCallback(): void
    {
        $exception = new TestException();
        $future = Future::resolve(1);
        $future = $future->apply(static fn () => throw $exception);
        $this->expectExceptionObject($exception);
        $future->await();
    }

    public function testCatchWithCompleteFuture(): void
    {
        $future = Future::resolve(1);
        $future = $future->catch($this->createCallback(0));
        self::assertSame(1, $future->await());
    }

    public function testCatchWithSuspendInCallback(): void
    {
        $future = Future::error(new TestException());
        $future = $future->catch(static fn (\Throwable $exception) => Future::resolve(1)->await());
        self::assertSame(1, $future->await());
    }
    public function testCatchWithPendingFuture(): void
    {
        $deferred = new Deferred;

        $future = $deferred->getFuture();
        $future = $future->catch(static fn (\Throwable $exception) => 1);

        EventLoop::delay(0.1, static fn () => $deferred->error(new TestException));

        self::assertSame(1, $future->await());
    }

    public function testCatchWithThrowingCallback(): void
    {
        $exception = new TestException();
        $future = Future::error(new \Error());
        $future = $future->catch(static fn () => throw $exception);
        $this->expectExceptionObject($exception);
        $future->await();
    }

    public function testFinallyWithCompleteFuture(): void
    {
        $future = Future::resolve(1);
        $future = $future->finally($this->createCallback(1));
        self::assertSame(1, $future->await());
    }

    public function testFinallyWithErrorFuture(): void
    {
        $exception = new TestException();
        $future = Future::error($exception);
        $future = $future->finally($this->createCallback(1));
        $this->expectExceptionObject($exception);
        $future->await();
    }

    public function testFinallyWithSuspendInCallback(): void
    {
        $future = Future::resolve(1);
        $future = $future->finally(static fn () => Future::resolve()->await());
        self::assertSame(1, $future->await());
    }
    public function testFinallyWithPendingFuture(): void
    {
        $deferred = new Deferred;

        $future = $deferred->getFuture();
        $future = $future->finally($this->createCallback(1));

        EventLoop::delay(0.1, static fn () => $deferred->resolve(1));

        self::assertSame(1, $future->await());
    }

    public function testFinallyWithThrowingCallback(): void
    {
        $exception = new TestException();
        $future = Future::resolve(1);
        $future = $future->finally(static fn () => throw $exception);
        $this->expectExceptionObject($exception);
        $future->await();
    }
}
