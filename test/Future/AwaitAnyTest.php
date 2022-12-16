<?php declare(strict_types=1);

namespace Amp\Future;

use Amp\CancelledException;
use Amp\CompositeException;
use Amp\DeferredFuture;
use Amp\Future;
use Amp\TimeoutCancellation;
use PHPUnit\Framework\TestCase;
use Revolt\EventLoop;

class AwaitAnyTest extends TestCase
{
    public function testSingleComplete(): void
    {
        self::assertSame(42, awaitAny([Future::complete(42)]));
    }

    public function testTwoComplete(): void
    {
        self::assertSame(1, awaitAny([Future::complete(1), Future::complete(2)]));
    }

    public function testTwoFirstPending(): void
    {
        $deferred = new DeferredFuture();

        self::assertSame(2, awaitAny([$deferred->getFuture(), Future::complete(2)]));
    }

    public function testTwoFirstThrowing(): void
    {
        self::assertSame(2, awaitAny([Future::error(new \Exception('foo')), Future::complete(2)]));
    }

    public function testTwoBothThrowing(): void
    {
        $this->expectException(CompositeException::class);
        $this->expectExceptionMessage('Multiple exceptions encountered (2); use "Amp\CompositeException::getReasons()" to retrieve the array of exceptions thrown:');

        Future\awaitAny([Future::error(new \Exception('foo')), Future::error(new \RuntimeException('bar'))]);
    }

    public function testTwoGeneratorThrows(): void
    {
        self::assertSame(2, awaitAny((static function () {
            yield Future::error(new \Exception('foo'));
            yield Future::complete(2);
        })()));
    }

    public function testCancellation(): void
    {
        $this->expectException(CancelledException::class);
        $deferreds = \array_map(function (int $value) {
            $deferred = new DeferredFuture;
            EventLoop::delay($value / 10, fn () => $deferred->complete($value));
            return $deferred;
        }, \range(1, 3));

        awaitAny(\array_map(
            fn (DeferredFuture $deferred) => $deferred->getFuture(),
            $deferreds
        ), new TimeoutCancellation(0.05));
    }

    public function testCompleteBeforeCancellation(): void
    {
        $deferreds = \array_map(function (int $value) {
            $deferred = new DeferredFuture;
            EventLoop::delay($value / 10, fn () => $deferred->complete($value));
            return $deferred;
        }, \range(1, 3));

        $deferred = new DeferredFuture;
        $deferred->error(new \Exception('foo'));

        \array_unshift($deferreds, $deferred);

        self::assertSame(1, awaitAny(\array_map(
            fn (DeferredFuture $deferred) => $deferred->getFuture(),
            $deferreds
        ), new TimeoutCancellation(0.2)));
    }
}
