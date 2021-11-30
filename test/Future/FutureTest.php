<?php

namespace Amp\Future;

use Amp\CancellationTokenSource;
use Amp\CancelledException;
use Amp\Deferred;
use Amp\Future;
use Amp\PHPUnit\AsyncTestCase;
use Amp\PHPUnit\LoopCaughtException;
use Amp\PHPUnit\TestException;
use Amp\TimeoutCancellationToken;
use Revolt\EventLoop;
use function Amp\delay;
use function Amp\launch;

class FutureTest extends AsyncTestCase
{
    public function testIterate(): void
    {
        $this->expectOutputString('a=1 b=0 c=2 ');

        $a = $this->delay(0.1, 'a');
        $b = $this->delay(0.2, 'b');
        $c = $this->delay(0.3, 'c');

        foreach (Future::iterate([$b, $a, $c]) as $index => $future) {
            print $future->await() . '=' . $index . ' ';
        }
    }

    public function testIterateGenerator(): void
    {
        $this->expectOutputString('a=1 ');

        /**
         * @var \Generator<int, Future<string>, void, void>
         */
        $iterator = (function () {
            yield (new Deferred)->getFuture();
            yield $this->delay(0.1, 'a');

            // Never joins
            (new Deferred)->getFuture()->await();
        })();

        foreach (Future::iterate($iterator) as $index => $future) {
            print $future->await() . '=' . $index . ' ';
            break;
        }
    }

    public function testComplete(): void
    {
        $deferred = new Deferred;
        $future = $deferred->getFuture();

        $deferred->complete('result');

        self::assertSame('result', $future->await());
    }

    public function testCompleteAsync(): void
    {
        $deferred = new Deferred;
        $future = $deferred->getFuture();

        EventLoop::delay(0.01, fn () => $deferred->complete('result'));

        self::assertSame('result', $future->await());
    }

    public function testCompleteImmediate(): void
    {
        $future = Future::complete('result');

        self::assertSame('result', $future->await());
    }

    public function testError(): void
    {
        $deferred = new Deferred;
        $future = $deferred->getFuture();

        $deferred->error(new \Exception('foo'));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('foo');

        $future->await();
    }

    public function testErrorAsync(): void
    {
        $deferred = new Deferred;
        $future = $deferred->getFuture();

        EventLoop::delay(0.01, fn () => $deferred->error(new \Exception('foo')));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('foo');

        $future->await();
    }

    public function testErrorImmediate(): void
    {
        $future = Future::error(new \Exception('foo'));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('foo');

        $future->await();
    }

    public function testCompleteWithFuture(): void
    {
        $deferred = new Deferred;

        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Cannot complete with an instance of');

        $deferred->complete(Future::complete());
    }

    public function testCancellation(): void
    {
        $future = $this->delay(0.02, true);

        $token = new TimeoutCancellationToken(0.01);

        $this->expectException(CancelledException::class);

        $future->await($token);
    }

    public function testCompleteBeforeCancellation(): void
    {
        $future = $this->delay(0.01, true);

        $token = new TimeoutCancellationToken(0.02);

        self::assertTrue($future->await($token));
    }

    public function testCompleteThenCancelJoin(): void
    {
        $deferred = new Deferred;
        $source = new CancellationTokenSource;
        $future = $deferred->getFuture();

        EventLoop::queue(function () use ($future, $source): void {
            self::assertSame(1, $future->await($source->getToken()));
        });

        $deferred->complete(1);
        $source->cancel();
    }


    public function testUnhandledError(): void
    {
        $deferred = new Deferred;
        $deferred->error(new TestException);
        unset($deferred);

        $this->expectException(LoopCaughtException::class);
    }

    public function testUnhandledErrorFromFutureError(): void
    {
        $future = Future::error(new TestException);
        unset($future);

        $this->expectException(LoopCaughtException::class);
    }

    public function testIgnoringUnhandledErrors(): void
    {
        $deferred = new Deferred;
        $deferred->getFuture()->ignore();
        $deferred->error(new TestException);
        unset($deferred);

        EventLoop::setErrorHandler($this->createCallback(0));
    }

    public function testIgnoreUnhandledErrorFromFutureError(): void
    {
        $future = Future::error(new TestException);
        $future->ignore();
        unset($future);

        EventLoop::setErrorHandler($this->createCallback(0));
    }

    public function testMapWithCompleteFuture(): void
    {
        $future = Future::complete(1);
        $future = $future->map(static fn (int $value) => $value + 1);
        self::assertSame(2, $future->await());
    }

    public function testMapWithSuspendInCallback(): void
    {
        $future = Future::complete(1);
        $future = $future->map(static fn (int $value) => $value + Future::complete(1)->await());
        self::assertSame(2, $future->await());
    }

    public function testMapWithPendingFuture(): void
    {
        $deferred = new Deferred;

        $future = $deferred->getFuture();
        $future = $future->map(static fn (int $value) => $value + 1);

        EventLoop::delay(0.1, static fn () => $deferred->complete(1));

        self::assertSame(2, $future->await());
    }

    public function testMapWithErrorFuture(): void
    {
        $future = Future::error($exception = new TestException());
        $future = $future->map($this->createCallback(0));
        $this->expectExceptionObject($exception);
        $future->await();
    }

    public function testMapWithThrowingCallback(): void
    {
        $exception = new TestException();
        $future = Future::complete(1);
        $future = $future->map(static fn () => throw $exception);
        $this->expectExceptionObject($exception);
        $future->await();
    }

    public function testCatchWithCompleteFuture(): void
    {
        $future = Future::complete(1);
        $future = $future->catch($this->createCallback(0));
        self::assertSame(1, $future->await());
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
        $future = $future->finally(static fn () => Future::complete()->await());
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

    /**
     * @template T
     *
     * @param float $seconds
     * @param T $value
     *
     * @return Future<T>
     */
    private function delay(float $seconds, mixed $value): Future
    {
        return launch(
            /**
             * @return T
             */
            static function () use ($seconds, $value): mixed {
                delay($seconds);
                return $value;
            }
        );
    }
}
