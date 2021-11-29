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

        $deferred->resolve('result');

        self::assertSame('result', $future->await());
    }

    public function testCompleteAsync(): void
    {
        $deferred = new Deferred;
        $future = $deferred->getFuture();

        EventLoop::delay(0.01, fn () => $deferred->resolve('result'));

        self::assertSame('result', $future->await());
    }

    public function testCompleteImmediate(): void
    {
        $future = Future::resolve('result');

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

        $deferred->resolve(1);
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

    public function testResolveWithCompleteFuture(): void
    {
        $deferred = new Deferred();
        $future = $deferred->getFuture();
        $deferred->resolve(Future::resolve(1));
        self::assertTrue($deferred->isResolved());
        self::assertFalse($future->isPending());
        self::assertSame(1, $future->await());
    }

    public function testResolveWithErrorFuture(): void
    {
        $exception = new TestException();
        $deferred = new Deferred();
        $deferred->resolve(Future::error($exception));
        self::assertTrue($deferred->isResolved());
        $this->expectExceptionObject($exception);
        $deferred->getFuture()->await();
    }

    public function testResolveWithPendingFuture(): void
    {
        $this->setMinimumRuntime(0.1);

        $deferred1 = new Deferred();
        $future1 = $deferred1->getFuture();
        $deferred2 = new Deferred();
        $future2 = $deferred2->getFuture();

        $deferred1->resolve($deferred2->getFuture());

        self::assertTrue($deferred1->isResolved());
        self::assertFalse($deferred2->isResolved());

        self::assertFalse($future1->isPending());
        self::assertFalse($future2->isPending());

        EventLoop::delay(0.1, static fn () => $deferred2->resolve(1));

        self::assertSame(1, $deferred2->getFuture()->await());
    }
}
