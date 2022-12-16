<?php declare(strict_types=1);

namespace Amp\Future;

use Amp\CancelledException;
use Amp\DeferredFuture;
use Amp\Future;
use Amp\TimeoutCancellation;
use PHPUnit\Framework\TestCase;
use Revolt\EventLoop;

class AwaitFirstTest extends TestCase
{
    public function testSingleComplete(): void
    {
        self::assertSame(42, awaitFirst([Future::complete(42)]));
    }

    public function testTwoComplete(): void
    {
        self::assertSame(1, Future\awaitFirst([Future::complete(1), Future::complete(2)]));
    }

    public function testTwoFirstPending(): void
    {
        $deferred = new DeferredFuture;

        self::assertSame(2, Future\awaitFirst([$deferred->getFuture(), Future::complete(2)]));
    }

    public function testTwoFirstThrowing(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('foo');

        awaitFirst([Future::error(new \Exception('foo')), Future::complete(2)]);
    }

    public function testTwoGeneratorThrows(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('foo');

        awaitFirst((static function () {
            yield Future::error(new \Exception('foo'));
            yield Future::complete(2);
        })());
    }

    public function testCancellation(): void
    {
        $this->expectException(CancelledException::class);

        $deferreds = \array_map(function (int $value) {
            $deferred = new DeferredFuture;
            EventLoop::delay($value / 10, fn () => $deferred->complete($value));
            return $deferred;
        }, \range(1, 3));

        awaitFirst(\array_map(
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

        self::assertSame(1, awaitFirst(\array_map(
            fn (DeferredFuture $deferred) => $deferred->getFuture(),
            $deferreds
        ), new TimeoutCancellation(0.2)));
    }
}
