<?php

namespace Amp\Test;

use Amp\Failure;
use Amp\Pause;
use Amp\Promise;
use Amp\Stream;
use Amp\Success;
use Amp\Loop;

class StreamFromIterableTest extends \PHPUnit\Framework\TestCase {
    const TIMEOUT = 10;

    public function testSuccessfulPromises() {
        $results = [];
        Loop::run(function () use (&$results) {
            $stream = Stream\fromIterable([new Success(1), new Success(2), new Success(3)]);

            $stream->onEmit(function ($value) use (&$results) {
                $results[] = $value;
            });
        });

        $this->assertSame([1, 2, 3], $results);
    }

    public function testFailedPromises() {
        $exception = new \Exception;
        Loop::run(function () use (&$reason, $exception) {
            $stream = Stream\fromIterable([new Failure($exception), new Failure($exception)]);

            $callback = function ($exception, $value) use (&$reason) {
                $reason = $exception;
            };

            $stream->onResolve($callback);
        });

        $this->assertSame($exception, $reason);
    }

    public function testMixedPromises() {
        $exception = new \Exception;
        $results = [];
        Loop::run(function () use (&$results, &$reason, $exception) {
            $stream = Stream\fromIterable([new Success(1), new Success(2), new Failure($exception), new Success(4)]);

            $stream->onEmit(function ($value) use (&$results) {
                $results[] = $value;
            });

            $callback = function ($exception, $value) use (&$reason) {
                $reason = $exception;
            };

            $stream->onResolve($callback);
        });

        $this->assertSame(\range(1, 2), $results);
        $this->assertSame($exception, $reason);
    }

    public function testPendingPromises() {
        $results = [];
        Loop::run(function () use (&$results) {
            $stream = Stream\fromIterable([new Pause(30, 1), new Pause(10, 2), new Pause(20, 3), new Success(4)]);

            $stream->onEmit(function ($value) use (&$results) {
                $results[] = $value;
            });
        });

        $this->assertSame(\range(1, 4), $results);
    }

    public function testTraversable() {
        $results = [];
        Loop::run(function () use (&$results) {
            $generator = (function () {
                foreach (\range(1, 4) as $value) {
                    yield $value;
                }
            })();

            $stream = Stream\fromIterable($generator);

            $stream->onEmit(function ($value) use (&$results) {
                $results[] = $value;
            });
        });

        $this->assertSame(\range(1, 4), $results);
    }

    /**
     * @expectedException \Amp\UnionTypeError
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
        $count = 3;
        $stream = Stream\fromIterable(range(1, $count), self::TIMEOUT);

        $i = 0;
        $stream = Stream\map($stream, function ($value) use (&$i) {
            $this->assertSame(++$i, $value);
        });

        Promise\wait($stream);

        $this->assertSame($count, $i);
    }

    /**
     * @depends testInterval
     */
    public function testSlowConsumer() {
        $invoked = 0;
        $count = 5;
        Loop::run(function () use (&$invoked, $count) {
            $stream = Stream\fromIterable(range(1, $count), self::TIMEOUT);

            $stream->onEmit(function () use (&$invoked) {
                ++$invoked;
                return new Pause(self::TIMEOUT * 2);
            });
        });

        $this->assertSame($count, $invoked);
    }
}
