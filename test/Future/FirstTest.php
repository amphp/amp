<?php

namespace Amp\Test\Future;

use Amp\CancelledException;
use Amp\Deferred;
use Amp\Future;
use Amp\TimeoutCancellationToken;
use PHPUnit\Framework\TestCase;
use Revolt\EventLoop\Loop;
use function Amp\Future\first;

class FirstTest extends TestCase
{
    public function testSingleComplete(): void
    {
        self::assertSame(42, first([Future::complete(42)]));
    }

    public function testTwoComplete(): void
    {
        self::assertSame(1, Future\first([Future::complete(1), Future::complete(2)]));
    }

    public function testTwoFirstPending(): void
    {
        $deferred = new Deferred;

        self::assertSame(2, Future\first([$deferred->getFuture(), Future::complete(2)]));
    }

    public function testTwoFirstThrowing(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('foo');

        first([Future::error(new \Exception('foo')), Future::complete(2)]);
    }

    public function testTwoGeneratorThrows(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('foo');

        first((static function () {
            yield Future::error(new \Exception('foo'));
            yield Future::complete(2);
        })());
    }

    public function testCancellation(): void
    {
        $this->expectException(CancelledException::class);

        $deferreds = \array_map(function (int $value) {
            $deferred = new Deferred;
            Loop::delay($value / 10, fn() => $deferred->complete($value));
            return $deferred;
        }, \range(1, 3));

        first(\array_map(
            fn(Deferred $deferred) => $deferred->getFuture(),
            $deferreds
        ), new TimeoutCancellationToken(0.05));
    }

    public function testCompleteBeforeCancellation(): void
    {
        $deferreds = \array_map(function (int $value) {
            $deferred = new Deferred;
            Loop::delay($value / 10, fn() => $deferred->complete($value));
            return $deferred;
        }, \range(1, 3));

        self::assertSame(1, first(\array_map(
            fn(Deferred $deferred) => $deferred->getFuture(),
            $deferreds
        ), new TimeoutCancellationToken(0.2)));
    }
}
