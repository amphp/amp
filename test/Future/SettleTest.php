<?php declare(strict_types=1);

namespace Amp\Future;

use Amp\CancelledException;
use Amp\CompositeException;
use Amp\DeferredFuture;
use Amp\Future;
use Amp\TimeoutCancellation;
use PHPUnit\Framework\TestCase;
use Revolt\EventLoop;

class SettleTest extends TestCase
{
    public function testEmpty(): void
    {
        self::assertSame([], settle([]));
    }

    public function testAllComplete(): void
    {
        $futures = [
            Future::complete(1),
            Future::complete(2),
        ];

        self::assertSame([1, 2], settle($futures));
    }

    public function testKeysPreserved(): void
    {
        $futures = [
            'one' => Future::complete(1),
            'two' => Future::complete(2),
        ];

        self::assertSame(
            ['one' => 1, 'two' => 2],
            settle($futures),
        );
    }

    public function testSingleError(): void
    {
        $exception = new \Exception('foo');
        $futures = [
            'one' => Future::error($exception),
            'two' => Future::complete(2),
        ];

        try {
            settle($futures);

            self::fail('Expected ' . CompositeException::class . ' to be thrown');
        } catch (CompositeException $composite) {
            self::assertSame(
                ['one' => $exception],
                $composite->getReasons(),
            );
        }
    }

    public function testMultipleErrors(): void
    {
        $first = new \Exception('foo');
        $second = new \RuntimeException('bar');
        $futures = [
            Future::error($first),
            Future::error($second),
        ];

        try {
            settle($futures);

            self::fail('Expected ' . CompositeException::class . ' to be thrown');
        } catch (CompositeException $composite) {
            self::assertSame(
                [$first, $second],
                $composite->getReasons(),
            );
        }
    }

    public function testWaitsForAllBeforeThrowing(): void
    {
        $immediate = new \Exception('foo');
        $delayed = new \RuntimeException('bar');

        $deferred = new DeferredFuture;
        EventLoop::delay(0.01, fn () => $deferred->error($delayed));

        $futures = [
            'delayed' => $deferred->getFuture(),
            'immediate' => Future::error($immediate),
        ];

        try {
            settle($futures);

            self::fail('Expected ' . CompositeException::class . ' to be thrown');
        } catch (CompositeException $composite) {
            $reasons = $composite->getReasons();

            self::assertSame([
                'immediate' => $immediate,
                'delayed' => $delayed,
            ], $reasons);
        }
    }

    public function testCancellation(): void
    {
        $this->expectException(CancelledException::class);

        $deferreds = \array_map(function (int $value) {
            $deferred = new DeferredFuture;
            EventLoop::delay($value / 10, fn () => $deferred->complete($value));
            return $deferred;
        }, \range(1, 3));

        settle(\array_map(
            fn (DeferredFuture $deferred) => $deferred->getFuture(),
            $deferreds
        ), new TimeoutCancellation(0.05));
    }
}
