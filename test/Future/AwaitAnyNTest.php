<?php

namespace Amp\Future;

use Amp\CancelledException;
use Amp\CompositeException;
use Amp\CompositeLengthException;
use Amp\DeferredFuture;
use Amp\Future;
use Amp\TimeoutCancellation;
use PHPUnit\Framework\TestCase;
use Revolt\EventLoop;

class AwaitAnyNTest extends TestCase
{
    public function testSingleComplete(): void
    {
        self::assertSame([0 => 42], awaitAnyN(1, [Future::complete(42)]));
    }

    public function testTwoComplete(): void
    {
        self::assertSame([1, 2], awaitAnyN(2, [Future::complete(1), Future::complete(2)]));
    }

    public function testTwoFirstPending(): void
    {
        $deferred = new DeferredFuture();

        self::assertSame([1 => 2], awaitAnyN(1, [$deferred->getFuture(), Future::complete(2)]));
    }

    public function testTwoFirstThrowing(): void
    {
        self::assertSame(
            ['two' => 2],
            awaitAnyN(1, ['one' => Future::error(new \Exception('foo')), 'two' => Future::complete(2)])
        );
    }

    public function testTwoBothThrowing(): void
    {
        $this->expectException(CompositeException::class);
        $this->expectExceptionMessage('Multiple exceptions encountered (2); use "Amp\CompositeException::getReasons()" to retrieve the array of exceptions thrown:');

        Future\awaitAnyN(2, [Future::error(new \Exception('foo')), Future::error(new \RuntimeException('bar'))]);
    }

    public function testTwoGeneratorThrows(): void
    {
        self::assertSame([1 => 2], awaitAnyN(1, (static function () {
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

        awaitAnyN(3, \array_map(
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

        self::assertSame(\range(1, 3), awaitAnyN(3, \array_map(
            fn (DeferredFuture $deferred) => $deferred->getFuture(),
            $deferreds
        ), new TimeoutCancellation(0.5)));
    }

    public function testZero(): void
    {
        $this->expectException(\ValueError::class);
        $this->expectExceptionMessage('greater than 0');
        awaitAnyN(0, []);
    }

    public function testTooFew(): void
    {
        $this->expectException(CompositeLengthException::class);
        $this->expectExceptionMessage('Argument #2 ($futures) contains too few futures to satisfy the required count of 3');
        awaitAnyN(3, [Future::complete(1), Future::complete(2)]);
    }
}
