<?php

namespace Amp\Future;

use Amp\CancelledException;
use Amp\CompositeException;
use Amp\Deferred;
use Amp\Future;
use Amp\TimeoutCancellationToken;
use PHPUnit\Framework\TestCase;
use Revolt\EventLoop;

class AnyTest extends TestCase
{
    public function testSingleComplete(): void
    {
        self::assertSame(42, any([Future::resolve(42)]));
    }

    public function testTwoComplete(): void
    {
        self::assertSame(1, any([Future::resolve(1), Future::resolve(2)]));
    }

    public function testTwoFirstPending(): void
    {
        $deferred = new Deferred();

        self::assertSame(2, any([$deferred->getFuture(), Future::resolve(2)]));
    }

    public function testTwoFirstThrowing(): void
    {
        self::assertSame(2, any([Future::error(new \Exception('foo')), Future::resolve(2)]));
    }

    public function testTwoBothThrowing(): void
    {
        $this->expectException(CompositeException::class);
        $this->expectExceptionMessage('Multiple errors encountered (2); use "Amp\CompositeException::getReasons()" to retrieve the array of exceptions thrown:');

        Future\any([Future::error(new \Exception('foo')), Future::error(new \RuntimeException('bar'))]);
    }

    public function testTwoGeneratorThrows(): void
    {
        self::assertSame(2, any((static function () {
            yield Future::error(new \Exception('foo'));
            yield Future::resolve(2);
        })()));
    }

    public function testCancellation(): void
    {
        $this->expectException(CancelledException::class);
        $deferreds = \array_map(function (int $value) {
            $deferred = new Deferred;
            EventLoop::delay($value / 10, fn () => $deferred->resolve($value));
            return $deferred;
        }, \range(1, 3));

        any(\array_map(
            fn (Deferred $deferred) => $deferred->getFuture(),
            $deferreds
        ), new TimeoutCancellationToken(0.05));
    }

    public function testCompleteBeforeCancellation(): void
    {
        $deferreds = \array_map(function (int $value) {
            $deferred = new Deferred;
            EventLoop::delay($value / 10, fn () => $deferred->resolve($value));
            return $deferred;
        }, \range(1, 3));

        $deferred = new Deferred;
        $deferred->error(new \Exception('foo'));

        \array_unshift($deferreds, $deferred);

        self::assertSame(1, any(\array_map(
            fn (Deferred $deferred) => $deferred->getFuture(),
            $deferreds
        ), new TimeoutCancellationToken(0.2)));
    }
}
