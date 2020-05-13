<?php

namespace Amp\Test\Stream;

use Amp\Delayed;
use Amp\Failure;
use Amp\Loop;
use Amp\PHPUnit\TestException;
use Amp\Stream;
use Amp\Success;
use Amp\Test\BaseTest;

class FromIterableTest extends BaseTest
{
    const TIMEOUT = 10;

    public function testSuccessfulPromises()
    {
        Loop::run(function () {
            $expected = \range(1, 3);
            $iterator = Stream\fromIterable([new Success(1), new Success(2), new Success(3)]);

            while (list($value) = yield $iterator->continue()) {
                $this->assertSame(\array_shift($expected), $value);
            }
        });
    }

    public function testFailedPromises()
    {
        Loop::run(function () {
            $exception = new \Exception;
            $iterator = Stream\fromIterable([new Failure($exception), new Failure($exception)]);

            try {
                yield $iterator->continue();
            } catch (\Exception $reason) {
                $this->assertSame($exception, $reason);
            }
        });
    }

    public function testMixedPromises()
    {
        Loop::run(function () {
            $exception = new TestException;
            $expected = \range(1, 2);
            $iterator = Stream\fromIterable([new Success(1), new Success(2), new Failure($exception), new Success(4)]);

            try {
                while (list($value) = yield $iterator->continue()) {
                    $this->assertSame(\array_shift($expected), $value);
                }
                $this->fail("A failed promise in the iterable should fail the iterator and be thrown from advance()");
            } catch (TestException $reason) {
                $this->assertSame($exception, $reason);
            }

            $this->assertEmpty($expected);
        });
    }

    public function testPendingPromises()
    {
        Loop::run(function () {
            $expected = \range(1, 4);
            $iterator = Stream\fromIterable([new Delayed(30, 1), new Delayed(10, 2), new Delayed(20, 3), new Success(4)]);

            while (list($value) = yield $iterator->continue()) {
                $this->assertSame(\array_shift($expected), $value);
            }
        });
    }

    public function testTraversable()
    {
        Loop::run(function () {
            $expected = \range(1, 4);
            $generator = (function () {
                foreach (\range(1, 4) as $value) {
                    yield $value;
                }
            })();

            $iterator = Stream\fromIterable($generator);

            while (list($value) = yield $iterator->continue()) {
                $this->assertSame(\array_shift($expected), $value);
            }

            $this->assertEmpty($expected);
        });
    }

    /**
     * @dataProvider provideInvalidIteratorArguments
     */
    public function testInvalid($arg)
    {
        $this->expectException(\TypeError::class);

        Stream\fromIterable($arg);
    }

    public function provideInvalidIteratorArguments()
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
        Loop::run(function () {
            $count = 3;
            $iterator = Stream\fromIterable(\range(1, $count), self::TIMEOUT);

            $i = 0;
            while (list($value) = yield $iterator->continue()) {
                $this->assertSame(++$i, $value);
            }

            $this->assertSame($count, $i);
        });
    }

    /**
     * @depends testInterval
     */
    public function testSlowConsumer()
    {
        $count = 5;
        Loop::run(function () use ($count) {
            $iterator = Stream\fromIterable(\range(1, $count), self::TIMEOUT);

            for ($i = 0; list($value) = yield $iterator->continue(); ++$i) {
                yield new Delayed(self::TIMEOUT * 2);
            }

            $this->assertSame($count, $i);
        });
    }
}
