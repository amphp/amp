<?php

namespace Amp\Test;

use Amp\Iterator;
use Amp\Loop;
use Amp\Producer;

class ConcatTest extends \PHPUnit\Framework\TestCase {
    public function getArrays() {
        return [
            [[\range(1, 3), \range(4, 6)], \range(1, 6)],
            [[\range(1, 5), \range(6, 8)], \range(1, 8)],
            [[\range(1, 4), \range(5, 10)], \range(1, 10)],
        ];
    }

    /**
     * @dataProvider getArrays
     *
     * @param array $streams
     * @param array $expected
     */
    public function testConcat(array $streams, array $expected) {
        Loop::run(function () use ($streams, $expected) {
            $streams = \array_map(function (array $stream): Iterator {
                return Iterator\fromIterable($stream);
            }, $streams);

            $stream = Iterator\concat($streams);

            while (yield $stream->advance()) {
                $this->assertSame(\array_shift($expected), $stream->getCurrent());
            }
        });
    }

    /**
     * @depends testConcat
     */
    public function testConcatWithFailedStream() {
        Loop::run(function () {
            $exception = new \Exception;
            $expected = \range(1, 6);
            $producer = new Producer(function (callable $emit) use ($exception) {
                yield $emit(6); // Emit once before failing.
                throw $exception;
            });

            $stream = Iterator\concat([Iterator\fromIterable(\range(1, 5)), $producer, Iterator\fromIterable(\range(7, 10))]);

            try {
                while (yield $stream->advance()) {
                    $this->assertSame(\array_shift($expected), $stream->getCurrent());
                }
            } catch (\Throwable $reason) {
                $this->assertSame($exception, $reason);
            }

            $this->assertEmpty($expected);
        });
    }

    /**
     * @expectedException \TypeError
     */
    public function testNonStream() {
        Iterator\concat([1]);
    }
}
