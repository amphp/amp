<?php

namespace Amp;

use Amp\PHPUnit\AsyncTestCase;
use Amp\PHPUnit\TestException;
use Revolt\EventLoop;

class FutureTest extends AsyncTestCase
{
    public function testThenWithCompleteFuture(): void
    {
        $future = Future::complete(1);
        $future = $future->then(static fn (int $value) => $value + 1, $this->createCallback(0));
        self::assertSame(2, $future->await());
    }

    public function testThenWithSuspendInCompleteCallback(): void
    {
        $future = Future::complete(1);
        $future = $future->then(
            static fn (int $value) => $value + Future::complete(1)->await(),
            $this->createCallback(0)
        );
        self::assertSame(2, $future->await());
    }

    public function testThenWithPendingFuture(): void
    {
        $deferred = new Deferred;

        $future = $deferred->getFuture();
        $future = $future->then(static fn (int $value) => $value + 1, $this->createCallback(0));

        EventLoop::delay(0.1, static fn () => $deferred->complete(1));

        self::assertSame(2, $future->await());
    }

    public function testThenWithErrorFutureAndNoErrorCallback(): void
    {
        $future = Future::error($exception = new TestException());
        $future = $future->then($this->createCallback(0));
        $this->expectExceptionObject($exception);
        $future->await();
    }

    public function testThenWithErrorFutureErrorCallback(): void
    {
        $future = Future::error($exception = new TestException());
        $future = $future->then($this->createCallback(0), static fn (\Throwable $throwable) => 1);
        self::assertSame(1, $future->await());
    }

    public function testThenWithThrowingCompleteCallback(): void
    {
        $exception = new TestException();
        $future = Future::complete(1);
        $future = $future->then(static fn () => throw $exception);
        $this->expectExceptionObject($exception);
        $future->await();
    }

    public function testThenWithSuspendInErrorCallback(): void
    {
        $future = Future::error(new TestException());
        $future = $future->then(
            $this->createCallback(0),
            static fn (\Throwable $exception) => Future::complete(1)->await()
        );
        self::assertSame(1, $future->await());
    }

    public function testThenWithThrowingErrorCallback(): void
    {
        $exception = new TestException();
        $future = Future::error(new \Error());
        $future = $future->then(null, static fn () => throw $exception);
        $this->expectExceptionObject($exception);
        $future->await();
    }

    public function testThenWithNoCallbacksComplete(): void
    {
        $future1 = Future::complete(1);
        $future2 = $future1->then();
        self::assertNotSame($future1, $future2);
        self::assertSame(1, $future2->await());
    }

    public function testThenWithNoCallbacksError(): void
    {
        $future1 = Future::error($exception = new TestException());
        $future2 = $future1->then();
        self::assertNotSame($future1, $future2);
        $this->expectExceptionObject($exception);
        $future2->await();
    }

    public function testCatchWithSuspendInCallback(): void
    {
        $future = Future::error(new TestException());
        $future = $future->catch(static fn (\Throwable $exception) => Future::complete(1)->await());
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
        $future = Future::complete(1);
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
        $future = Future::complete(1);
        $future = $future->finally(static fn () => Future::complete(null)->await());
        self::assertSame(1, $future->await());
    }

    public function testFinallyWithPendingFuture(): void
    {
        $deferred = new Deferred;

        $future = $deferred->getFuture();
        $future = $future->finally($this->createCallback(1));

        EventLoop::delay(0.1, static fn () => $deferred->complete(1));

        self::assertSame(1, $future->await());
    }

    public function testFinallyWithThrowingCallback(): void
    {
        $exception = new TestException();
        $future = Future::complete(1);
        $future = $future->finally(static fn () => throw $exception);
        $this->expectExceptionObject($exception);
        $future->await();
    }
}
