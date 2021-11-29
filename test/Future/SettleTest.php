<?php

namespace Amp\Future;

use Amp\CancelledException;
use Amp\Deferred;
use Amp\Future;
use Amp\TimeoutCancellationToken;
use PHPUnit\Framework\TestCase;
use Revolt\EventLoop;

class SettleTest extends TestCase
{
    public function testSingleComplete(): void
    {
        self::assertSame([[], [42]], settle([Future::resolve(42)]));
    }

    public function testTwoComplete(): void
    {
        self::assertSame([[], [1, 2]], settle([Future::resolve(1), Future::resolve(2)]));
    }

    public function testTwoFirstThrowing(): void
    {
        $exception = new \Exception('foo');
        self::assertSame(
            [['one' => $exception], ['two' => 2]],
            settle(['one' => Future::error($exception), 'two' => Future::resolve(2)])
        );
    }

    public function testTwoBothThrowing(): void
    {
        $one = new \Exception('foo');
        $two = new \RuntimeException('bar');
        self::assertSame([[$one, $two], []], Future\settle([Future::error($one), Future::error($two)]));
    }

    public function testTwoGeneratorThrows(): void
    {
        $exception = new \Exception('foo');
        self::assertSame([[0 => $exception], [1 => 2]], settle((static function () use ($exception) {
            yield Future::error($exception);
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

        settle(\array_map(
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

        self::assertSame([[], \range(1, 3)], settle(\array_map(
            fn (Deferred $deferred) => $deferred->getFuture(),
            $deferreds
        ), new TimeoutCancellationToken(0.5)));
    }
}
