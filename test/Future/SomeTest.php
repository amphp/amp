<?php

namespace Amp\Future;

use Amp\CancelledException;
use Amp\CompositeException;
use Amp\Deferred;
use Amp\Future;
use Amp\TimeoutCancellationToken;
use PHPUnit\Framework\TestCase;
use Revolt\EventLoop;

class SomeTest extends TestCase
{
    public function testSingleComplete(): void
    {
        self::assertSame([0 => 42], some([Future::resolve(42)], 1));
    }

    public function testTwoComplete(): void
    {
        self::assertSame([1, 2], some([Future::resolve(1), Future::resolve(2)], 2));
    }

    public function testTwoFirstPending(): void
    {
        $deferred = new Deferred();

        self::assertSame([1 => 2], some([$deferred->getFuture(), Future::resolve(2)], 1));
    }

    public function testTwoFirstThrowing(): void
    {
        self::assertSame(['two' => 2], some(['one' => Future::error(new \Exception('foo')), 'two' => Future::resolve(2)], 1));
    }

    public function testTwoBothThrowing(): void
    {
        $this->expectException(CompositeException::class);
        $this->expectExceptionMessage('Multiple errors encountered (2); use "Amp\CompositeException::getReasons()" to retrieve the array of exceptions thrown:');

        Future\some([Future::error(new \Exception('foo')), Future::error(new \RuntimeException('bar'))], 2);
    }

    public function testTwoGeneratorThrows(): void
    {
        self::assertSame([1 => 2], some((static function () {
            yield Future::error(new \Exception('foo'));
            yield Future::resolve(2);
        })(), 1));
    }

    public function testCancellation(): void
    {
        $this->expectException(CancelledException::class);
        $deferreds = \array_map(function (int $value) {
            $deferred = new Deferred;
            EventLoop::delay($value / 10, fn () => $deferred->resolve($value));
            return $deferred;
        }, \range(1, 3));

        some(\array_map(
            fn (Deferred $deferred) => $deferred->getFuture(),
            $deferreds
        ), 3, new TimeoutCancellationToken(0.05));
    }

    public function testCompleteBeforeCancellation(): void
    {
        $deferreds = \array_map(function (int $value) {
            $deferred = new Deferred;
            EventLoop::delay($value / 10, fn () => $deferred->resolve($value));
            return $deferred;
        }, \range(1, 3));

        self::assertSame(\range(1, 3), some(\array_map(
            fn (Deferred $deferred) => $deferred->getFuture(),
            $deferreds
        ), 3, new TimeoutCancellationToken(0.5)));
    }

    public function testZero(): void
    {
        $this->expectException(\ValueError::class);
        $this->expectExceptionMessage('greater than 0');
        some([], 0);
    }

    public function testTooFew(): void
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('required count');
        some([Future::resolve(1), Future::resolve(2)], 3);
    }
}
