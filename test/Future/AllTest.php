<?php

namespace Amp\Test\Future;

use Amp\Deferred;
use Amp\Future;
use PHPUnit\Framework\TestCase;
use Revolt\EventLoop\Loop;
use function Amp\Future\all;

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

        Loop::delay(0.01, fn () => $deferred->complete(1));

        self::assertSame([1, 2], all([$deferred->getFuture(), Future::complete(2)]));
    }

    public function testTwoFirstThrowing(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('foo');

        all([Future::error(new \Exception('foo')), Future::complete(2)]);
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
}
