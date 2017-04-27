<?php

namespace Amp\Test;

use Amp\Failure;
use Amp\Loop;
use Amp\Pause;
use Amp\Promise;
use Amp\Stream;
use Amp\Success;

class StreamFromIterableTest extends \PHPUnit\Framework\TestCase {
    const TIMEOUT = 10;

    public function testSuccessfulPromises() {
        Loop::run(function () {
            $expected = \range(1, 3);
            $stream = Stream\fromIterable([new Success(1), new Success(2), new Success(3)]);

            while (yield $stream->advance()) {
                $this->assertSame(\array_shift($expected), $stream->getCurrent());
            }
        });
    }

    public function testFailedPromises() {
        Loop::run(function () {
            $exception = new \Exception;
            $stream = Stream\fromIterable([new Failure($exception), new Failure($exception)]);

            try {
                yield $stream->advance();
            } catch (\Exception $reason) {
                $this->assertSame($exception, $reason);
            }
        });
    }

    public function testMixedPromises() {

        Loop::run(function () {
            $exception = new \Exception;
            $expected = \range(1, 2);
            $stream = Stream\fromIterable([new Success(1), new Success(2), new Failure($exception), new Success(4)]);

            try {
                while (yield $stream->advance()) {
                    $this->assertSame(\array_shift($expected), $stream->getCurrent());
                }
            } catch (\Exception $reason) {
                $this->assertSame($exception, $reason);
            }

            $this->assertEmpty($expected);
        });
    }

    public function testPendingPromises() {
        Loop::run(function () {
            $expected = \range(1, 4);
            $stream = Stream\fromIterable([new Pause(30, 1), new Pause(10, 2), new Pause(20, 3), new Success(4)]);

            while (yield $stream->advance()) {
                $this->assertSame(\array_shift($expected), $stream->getCurrent());
            }
        });
    }

    public function testTraversable() {
        Loop::run(function () {
            $expected = \range(1, 4);
            $generator = (function () {
                foreach (\range(1, 4) as $value) {
                    yield $value;
                }
            })();

            $stream = Stream\fromIterable($generator);

            while (yield $stream->advance()) {
                $this->assertSame(\array_shift($expected), $stream->getCurrent());
            }

            $this->assertEmpty($expected);
        });
    }

    /**
     * @expectedException \TypeError
     * @dataProvider provideInvalidStreamArguments
     */
    public function testInvalid($arg) {
        Stream\fromIterable($arg);
    }

    public function provideInvalidStreamArguments() {
        return [
            [null],
            [new \stdClass],
            [32],
            [false],
            [true],
            ["string"],
        ];
    }

    public function testInterval() {
        Loop::run(function () {
            $count = 3;
            $stream = Stream\fromIterable(range(1, $count), self::TIMEOUT);

            $i = 0;
            while (yield $stream->advance()) {
                $this->assertSame(++$i, $stream->getCurrent());
            }

            $this->assertSame($count, $i);
        });
    }

    /**
     * @depends testInterval
     */
    public function testSlowConsumer() {
        $count = 5;
        Loop::run(function () use ($count) {
            $stream = Stream\fromIterable(range(1, $count), self::TIMEOUT);

            for ($i = 0; yield $stream->advance(); ++$i) {
                yield new Pause(self::TIMEOUT * 2);
            }

            $this->assertSame($count, $i);
        });
    }
}
