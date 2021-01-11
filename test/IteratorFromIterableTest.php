<?php

namespace Amp\Test;

use Amp\Delayed;
use Amp\Failure;
use Amp\Iterator;
use Amp\Loop;
use Amp\PHPUnit\TestException;
use Amp\Success;

class IteratorFromIterableTest extends BaseTest
{
    public const TIMEOUT = 10;

    public function testSuccessfulPromises(): void
    {
        Loop::run(function () {
            $expected = \range(1, 3);
            $iterator = Iterator\fromIterable([new Success(1), new Success(2), new Success(3)]);

            while (yield $iterator->advance()) {
                $this->assertSame(\array_shift($expected), $iterator->getCurrent());
            }
        });
    }

    public function testFailedPromises(): void
    {
        Loop::run(function () {
            $exception = new \Exception;
            $iterator = Iterator\fromIterable([new Failure($exception), new Failure($exception)]);

            try {
                yield $iterator->advance();
            } catch (\Exception $reason) {
                $this->assertSame($exception, $reason);
            }
        });
    }

    public function testMixedPromises(): void
    {
        Loop::run(function () {
            $exception = new TestException;
            $expected = \range(1, 2);
            $iterator = Iterator\fromIterable([
                new Success(1),
                new Success(2),
                new Failure($exception),
                new Success(4),
            ]);

            try {
                while (yield $iterator->advance()) {
                    $this->assertSame(\array_shift($expected), $iterator->getCurrent());
                }
                $this->fail("A failed promise in the iterable should fail the iterator and be thrown from advance()");
            } catch (TestException $reason) {
                $this->assertSame($exception, $reason);
            }

            $this->assertEmpty($expected);
        });
    }

    public function testPendingPromises(): void
    {
        Loop::run(function () {
            $expected = \range(1, 4);
            $iterator = Iterator\fromIterable([
                new Delayed(30, 1),
                new Delayed(10, 2),
                new Delayed(20, 3),
                new Success(4),
            ]);

            while (yield $iterator->advance()) {
                $this->assertSame(\array_shift($expected), $iterator->getCurrent());
            }
        });
    }

    public function testTraversable(): void
    {
        Loop::run(function () {
            $expected = \range(1, 4);
            $generator = (function () {
                foreach (\range(1, 4) as $value) {
                    yield $value;
                }
            })();

            $iterator = Iterator\fromIterable($generator);

            while (yield $iterator->advance()) {
                $this->assertSame(\array_shift($expected), $iterator->getCurrent());
            }

            $this->assertEmpty($expected);
        });
    }

    /**
     * @dataProvider provideInvalidIteratorArguments
     */
    public function testInvalid($arg): void
    {
        $this->expectException(\TypeError::class);

        Iterator\fromIterable($arg);
    }

    public function provideInvalidIteratorArguments(): array
    {
        return [
            [null],
            [new \stdClass],
            [32],
            [false],
            [true],
            ["string"],
        ];
    }

    public function testInterval(): void
    {
        Loop::run(function () {
            $count = 3;
            $iterator = Iterator\fromIterable(\range(1, $count), self::TIMEOUT);

            $i = 0;
            while (yield $iterator->advance()) {
                $this->assertSame(++$i, $iterator->getCurrent());
            }

            $this->assertSame($count, $i);
        });
    }

    /**
     * @depends testInterval
     */
    public function testSlowConsumer(): void
    {
        $count = 5;
        Loop::run(function () use ($count) {
            $iterator = Iterator\fromIterable(\range(1, $count), self::TIMEOUT);

            for ($i = 0; yield $iterator->advance(); ++$i) {
                yield new Delayed(self::TIMEOUT * 2);
            }

            $this->assertSame($count, $i);
        });
    }
}
