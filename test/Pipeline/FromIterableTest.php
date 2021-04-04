<?php

namespace Amp\Test\Pipeline;

use Amp\PHPUnit\AsyncTestCase;
use Amp\PHPUnit\TestException;
use Amp\Pipeline;
use Revolt\Future\Future;
use function Revolt\EventLoop\delay;
use function Revolt\Future\spawn;

class FromIterableTest extends AsyncTestCase
{
    const TIMEOUT = 10;

    public function testSuccessfulPromises(): void
    {
        $expected = \range(1, 3);
        $pipeline = Pipeline\fromIterable([Future::complete(1), Future::complete(2), Future::complete(3)]);

        while (null !== $value = $pipeline->continue()) {
            self::assertSame(\array_shift($expected), $value);
        }
    }

    public function testFailedPromises(): void
    {
        $exception = new \Exception;
        $iterator = Pipeline\fromIterable([Future::error($exception), Future::error($exception)]);

        $this->expectExceptionObject($exception);

        $iterator->continue();
    }

    public function testMixedPromises(): void
    {
        $exception = new TestException;
        $expected = \range(1, 2);
        $pipeline = Pipeline\fromIterable([Future::complete(1), Future::complete(2), Future::error($exception), Future::complete(4)]);

        try {
            while (null !== $value = $pipeline->continue()) {
                self::assertSame(\array_shift($expected), $value);
            }
            self::fail("A failed promise in the iterable should fail the pipeline and be thrown from continue()");
        } catch (TestException $reason) {
            self::assertSame($exception, $reason);
        }

        self::assertEmpty($expected);
    }

    public function testPendingPromises(): void
    {
        $expected = \range(1, 4);
        $pipeline = Pipeline\fromIterable([
            $this->asyncValue(30, 1),
            $this->asyncValue(10, 2),
            $this->asyncValue(20, 3),
            Future::complete(4),
        ]);

        while (null !== $value = $pipeline->continue()) {
            self::assertSame(\array_shift($expected), $value);
        }
    }

    public function testTraversable(): void
    {
        $expected = \range(1, 4);
        $generator = (static function () {
            foreach (\range(1, 4) as $value) {
                yield $value;
            }
        })();

        $pipeline = Pipeline\fromIterable($generator);

        while (null !== $value = $pipeline->continue()) {
            self::assertSame(\array_shift($expected), $value);
        }

        self::assertEmpty($expected);
    }

    /**
     * @dataProvider provideInvalidIteratorArguments
     */
    public function testInvalid($arg): void
    {
        $this->expectException(\TypeError::class);

        Pipeline\fromIterable($arg);
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
        $count = 3;
        $pipeline = Pipeline\fromIterable(\range(1, $count), self::TIMEOUT);

        $i = 0;
        while (null !== $value = $pipeline->continue()) {
            self::assertSame(++$i, $value);
        }

        self::assertSame($count, $i);
    }

    /**
     * @depends testInterval
     */
    public function testSlowConsumer(): void
    {
        $count = 5;
        $pipeline = Pipeline\fromIterable(\range(1, $count), self::TIMEOUT);

        for ($i = 0; $value = $pipeline->continue(); ++$i) {
            delay(self::TIMEOUT * 2);
        }

        self::assertSame($count, $i);
    }

    private function asyncValue(int $delay, mixed $value): Future
    {
        return spawn(static function () use ($delay, $value): mixed {
            delay($delay);
            return $value;
        });
    }
}
