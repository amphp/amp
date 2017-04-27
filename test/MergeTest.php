<?php

namespace Amp\Test;

use Amp\Loop;
use Amp\Pause;
use Amp\Producer;
use Amp\Stream;

class MergeTest extends \PHPUnit\Framework\TestCase {
    public function getArrays() {
        return [
            [[\range(1, 3), \range(4, 6)], [1, 4, 2, 5, 3, 6]],
            [[\range(1, 5), \range(6, 8)], [1, 6, 2, 7, 3, 8, 4, 5]],
            [[\range(1, 4), \range(5, 10)], [1, 5, 2, 6, 3, 7, 4, 8, 9, 10]],
        ];
    }

    /**
     * @dataProvider getArrays
     *
     * @param array $streams
     * @param array $expected
     */
    public function testMerge(array $streams, array $expected) {
        Loop::run(function () use ($streams, $expected) {
            $streams = \array_map(function (array $stream): Stream {
                return Stream\fromIterable($stream);
            }, $streams);

            $stream = Stream\merge($streams);

            while (yield $stream->advance()) {
                $this->assertSame(\array_shift($expected), $stream->getCurrent());
            }
        });
    }

    /**
     * @depends testMerge
     */
    public function testMergeWithDelayedEmits() {
        Loop::run(function () {
            $streams = [];
            $values1 = [new Pause(10, 1), new Pause(50, 2), new Pause(70, 3)];
            $values2 = [new Pause(20, 4), new Pause(40, 5), new Pause(60, 6)];
            $expected = [1, 4, 5, 2, 6, 3];

            $streams[] = new Producer(function (callable $emit) use ($values1) {
                foreach ($values1 as $value) {
                    yield $emit($value);
                }
            });

            $streams[] = new Producer(function (callable $emit) use ($values2) {
                foreach ($values2 as $value) {
                    yield $emit($value);
                }
            });

            $stream = Stream\merge($streams);

            while (yield $stream->advance()) {
                $this->assertSame(\array_shift($expected), $stream->getCurrent());
            }
        });
    }

    /**
     * @depends testMerge
     */
    public function testMergeWithFailedStream() {
        Loop::run(function () {
            $exception = new \Exception;
            $producer = new Producer(function (callable $emit) use ($exception) {
                yield $emit(1); // Emit once before failing.
                throw $exception;
            });

            $stream = Stream\merge([$producer, Stream\fromIterable(\range(1, 5))]);

            try {
                while (yield $stream->advance());
            } catch (\Throwable $reason) {
                $this->assertSame($exception, $reason);
            }
        });
    }

    /**
     * @expectedException \TypeError
     */
    public function testNonStream() {
        Stream\merge([1]);
    }
}
