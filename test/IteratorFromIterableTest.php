<?php

namespace Amp\Test;

use Amp\Failure;
use Amp\Iterator;
use Amp\Loop;
use Amp\Pause;
use Amp\Success;

class IteratorFromIterableTest extends \PHPUnit\Framework\TestCase {
    const TIMEOUT = 10;

    public function testSuccessfulPromises() {
        Loop::run(function () {
            $expected = \range(1, 3);
            $iterator = Iterator\fromIterable([new Success(1), new Success(2), new Success(3)]);

            while (yield $iterator->advance()) {
                $this->assertSame(\array_shift($expected), $iterator->getCurrent());
            }
        });
    }

    public function testFailedPromises() {
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

    public function testMixedPromises() {
        Loop::run(function () {
            $exception = new \Exception;
            $expected = \range(1, 2);
            $iterator = Iterator\fromIterable([new Success(1), new Success(2), new Failure($exception), new Success(4)]);

            try {
                while (yield $iterator->advance()) {
                    $this->assertSame(\array_shift($expected), $iterator->getCurrent());
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
            $iterator = Iterator\fromIterable([new Pause(30, 1), new Pause(10, 2), new Pause(20, 3), new Success(4)]);

            while (yield $iterator->advance()) {
                $this->assertSame(\array_shift($expected), $iterator->getCurrent());
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

            $iterator = Iterator\fromIterable($generator);

            while (yield $iterator->advance()) {
                $this->assertSame(\array_shift($expected), $iterator->getCurrent());
            }

            $this->assertEmpty($expected);
        });
    }

    /**
     * @expectedException \TypeError
     * @dataProvider provideInvalidStreamArguments
     */
    public function testInvalid($arg) {
        Iterator\fromIterable($arg);
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
            $iterator = Iterator\fromIterable(range(1, $count), self::TIMEOUT);

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
    public function testSlowConsumer() {
        $count = 5;
        Loop::run(function () use ($count) {
            $iterator = Iterator\fromIterable(range(1, $count), self::TIMEOUT);

            for ($i = 0; yield $iterator->advance(); ++$i) {
                yield new Pause(self::TIMEOUT * 2);
            }

            $this->assertSame($count, $i);
        });
    }
}
