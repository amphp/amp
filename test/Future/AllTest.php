<?php

namespace Amp\Future;

use Amp\CancelledException;
use Amp\Deferred;
use Amp\Future;
use Amp\TimeoutCancellationToken;
use PHPUnit\Framework\TestCase;
use Revolt\EventLoop;

class AllTest extends TestCase
{
    public function testSingleComplete(): void
    {
        self::assertSame([42], all([Future::complete(42)]));
    }

    public function testTwoComplete(): void
    {
        self::assertSame([1, 2], all([Future::complete(1), Future::complete(2)]));
    }

    public function testTwoFirstPending(): void
    {
        $deferred = new Deferred;

        EventLoop::delay(0.01, fn () => $deferred->complete(1));

        self::assertSame([1 => 2, 0 => 1], all([$deferred->getFuture(), Future::complete(2)]));
    }

    public function testArrayDestructuring(): void
    {
        $deferred = new Deferred;

        EventLoop::delay(0.01, fn () => $deferred->complete(1));

        [$first, $second] = all([$deferred->getFuture(), Future::complete(2)]);

        self::assertSame(1, $first);
        self::assertSame(2, $second);
    }

    public function testTwoFirstThrowing(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('foo');

        all([Future::error(new \Exception('foo')), Future::complete(2)]);
    }

    public function testTwoThrowingWithOneLater(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('foo');

        $deferred = new Deferred;
        EventLoop::delay(0.1, static fn () => $deferred->error(new \Exception('bar')));

        all([Future::error(new \Exception('foo')), $deferred->getFuture()]);
    }

    public function testTwoGeneratorThrows(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('foo');

        all((static function () {
            yield Future::error(new \Exception('foo'));
            yield Future::complete(2);
        })());
    }

    public function testCancellation(): void
    {
        $this->expectException(CancelledException::class);
        $deferreds = \array_map(function (int $value) {
            $deferred = new Deferred;
            EventLoop::delay($value / 10, fn () => $deferred->complete($value));
            return $deferred;
        }, \range(1, 3));

        all(\array_map(
            fn (Deferred $deferred) => $deferred->getFuture(),
            $deferreds
        ), new TimeoutCancellationToken(0.2));
    }

    public function testCompleteBeforeCancellation(): void
    {
        $deferreds = \array_map(function (int $value) {
            $deferred = new Deferred;
            EventLoop::delay($value / 10, fn () => $deferred->complete($value));
            return $deferred;
        }, \range(1, 3));

        self::assertSame([1, 2, 3], all(\array_map(
            fn (Deferred $deferred) => $deferred->getFuture(),
            $deferreds
        ), new TimeoutCancellationToken(0.5)));
    }
}
