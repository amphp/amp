<?php

namespace Amp\Test\Stream;

use Amp\Delayed;
use Amp\Failure;
use Amp\PHPUnit\AsyncTestCase;
use Amp\PHPUnit\TestException;
use Amp\Stream;
use Amp\Success;

class FromIterableTest extends AsyncTestCase
{
    const TIMEOUT = 10;

    public function testSuccessfulPromises()
    {
        $expected = \range(1, 3);
        $stream = Stream\fromIterable([new Success(1), new Success(2), new Success(3)]);

        while ($value = yield $stream->continue()) {
            $this->assertSame(\array_shift($expected), $value->unwrap());
        }
    }

    public function testFailedPromises()
    {
        $exception = new \Exception;
        $iterator = Stream\fromIterable([new Failure($exception), new Failure($exception)]);

        $this->expectExceptionObject($exception);

        yield $iterator->continue();
    }

    public function testMixedPromises()
    {
        $exception = new TestException;
        $expected = \range(1, 2);
        $stream = Stream\fromIterable([new Success(1), new Success(2), new Failure($exception), new Success(4)]);

        try {
            while ($value = yield $stream->continue()) {
                $this->assertSame(\array_shift($expected), $value->unwrap());
            }
            $this->fail("A failed promise in the iterable should fail the stream and be thrown from continue()");
        } catch (TestException $reason) {
            $this->assertSame($exception, $reason);
        }

        $this->assertEmpty($expected);
    }

    public function testPendingPromises()
    {
        $expected = \range(1, 4);
        $stream = Stream\fromIterable([
            new Delayed(30, 1),
            new Delayed(10, 2),
            new Delayed(20, 3),
            new Success(4),
        ]);

        while ($value = yield $stream->continue()) {
            $this->assertSame(\array_shift($expected), $value->unwrap());
        }
    }

    public function testTraversable()
    {
        $expected = \range(1, 4);
        $generator = (static function () {
            foreach (\range(1, 4) as $value) {
                yield $value;
            }
        })();

        $stream = Stream\fromIterable($generator);

        while ($value = yield $stream->continue()) {
            $this->assertSame(\array_shift($expected), $value->unwrap());
        }

        $this->assertEmpty($expected);
    }

    /**
     * @dataProvider provideInvalidIteratorArguments
     */
    public function testInvalid($arg)
    {
        $this->expectException(\TypeError::class);

        Stream\fromIterable($arg);
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

    public function testInterval()
    {
        $count = 3;
        $stream = Stream\fromIterable(\range(1, $count), self::TIMEOUT);

        $i = 0;
        while ($value = yield $stream->continue()) {
            $this->assertSame(++$i, $value->unwrap());
        }

        $this->assertSame($count, $i);
    }

    /**
     * @depends testInterval
     */
    public function testSlowConsumer()
    {
        $count = 5;
        $stream = Stream\fromIterable(\range(1, $count), self::TIMEOUT);

        for ($i = 0; $value = yield $stream->continue(); ++$i) {
            yield new Delayed(self::TIMEOUT * 2);
        }

        $this->assertSame($count, $i);
    }
}
