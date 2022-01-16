<?php

namespace Amp\Future;

use Amp\CancelledException;
use Amp\DeferredFuture;
use Amp\Future;
use Amp\TimeoutCancellation;
use PHPUnit\Framework\TestCase;
use Revolt\EventLoop;

class AwaitAllTest extends TestCase
{
    public function testSingleComplete(): void
    {
        self::assertSame([[], [42]], awaitAll([Future::complete(42)]));
    }

    public function testTwoComplete(): void
    {
        self::assertSame([[], [1, 2]], awaitAll([Future::complete(1), Future::complete(2)]));
    }

    public function testTwoFirstThrowing(): void
    {
        $exception = new \Exception('foo');
        self::assertSame(
            [['one' => $exception], ['two' => 2]],
            awaitAll(['one' => Future::error($exception), 'two' => Future::complete(2)])
        );
    }

    public function testTwoBothThrowing(): void
    {
        $one = new \Exception('foo');
        $two = new \RuntimeException('bar');
        self::assertSame([[$one, $two], []], Future\awaitAll([Future::error($one), Future::error($two)]));
    }

    public function testTwoGeneratorThrows(): void
    {
        $exception = new \Exception('foo');
        self::assertSame([[0 => $exception], [1 => 2]], awaitAll((static function () use ($exception) {
            yield Future::error($exception);
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

        awaitAll(\array_map(
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

        self::assertSame([[], \range(1, 3)], awaitAll(\array_map(
            fn (DeferredFuture $deferred) => $deferred->getFuture(),
            $deferreds
        ), new TimeoutCancellation(0.5)));
    }
}
