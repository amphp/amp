<?php

namespace Amp\Test;

use Amp;
use Amp\Loop;
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
        $streams = \array_map(function (array $stream): Stream {
            return Amp\stream($stream);
        }, $streams);

        $stream = Amp\merge($streams);

        Amp\each($stream, function ($value) use ($expected) {
            static $i = 0;
            $this->assertSame($expected[$i++], $value);
        });

        Loop::run();
    }

    /**
     * @depends testMerge
     */
    public function testMergeWithFailedStream() {
        $exception = new \Exception;
        Loop::run(function () use (&$reason, $exception) {
            $producer = new Producer(function (callable $emit) use ($exception) {
                yield $emit(1); // Emit once before failing.
                throw $exception;
            });

            $stream = Amp\merge([$producer, Amp\stream(\range(1, 5))]);

            $callback = function ($exception, $value) use (&$reason) {
                $reason = $exception;
            };

            $stream->when($callback);
        });

        $this->assertSame($exception, $reason);
    }

    /**
     * @expectedException \Amp\UnionTypeError
     */
    public function testNonStream() {
        Amp\merge([1]);
    }
}
