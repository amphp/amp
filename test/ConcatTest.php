<?php

namespace Amp\Test;

use Amp;
use Amp\Loop;
use Amp\Producer;
use Amp\Stream;

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
        $streams = \array_map(function (array $stream): Stream {
            return Amp\stream($stream);
        }, $streams);

        $stream = Amp\concat($streams);

        Amp\each($stream, function ($value) use ($expected) {
            static $i = 0;
            $this->assertSame($expected[$i++], $value);
        });

        Loop::run();
    }

    /**
     * @depends testConcat
     */
    public function testConcatWithFailedStream() {
        $exception = new \Exception;
        $results = [];
        Loop::run(function () use (&$results, &$reason, $exception) {
            $producer = new Producer(function (callable $emit) use ($exception) {
                yield $emit(6); // Emit once before failing.
                throw $exception;
            });

            $stream = Amp\concat([Amp\stream(\range(1, 5)), $producer, Amp\stream(\range(7, 10))]);

            $stream->listen(function ($value) use (&$results) {
                $results[] = $value;
            });

            $callback = function ($exception, $value) use (&$reason) {
                $reason = $exception;
            };

            $stream->when($callback);
        });

        $this->assertSame(\range(1, 6), $results);
        $this->assertSame($exception, $reason);
    }

    /**
     * @expectedException \Amp\UnionTypeError
     */
    public function testNonStream() {
        Amp\concat([1]);
    }
}
