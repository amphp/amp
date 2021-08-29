<?php

namespace Amp\Test\Future;

use Amp\CompositeException;
use Amp\Deferred;
use Amp\Future;
use PHPUnit\Framework\TestCase;
use function Amp\Future\any;

class AnyTest extends TestCase
{
    public function testSingleComplete(): void
    {
        self::assertSame(42, any([Future::complete(42)]));
    }

    public function testTwoComplete(): void
    {
        self::assertSame(1, any([Future::complete(1), Future::complete(2)]));
    }

    public function testTwoFirstPending(): void
    {
        $deferred = new Deferred();

        self::assertSame(2, any([$deferred->getFuture(), Future::complete(2)]));
    }

    public function testTwoFirstThrowing(): void
    {
        self::assertSame(2, any([Future::error(new \Exception('foo')), Future::complete(2)]));
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
            yield Future::complete(2);
        })()));
    }
}
