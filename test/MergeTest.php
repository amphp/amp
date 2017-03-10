<?php

namespace Amp\Test;

use Amp;
use Amp\Producer;
use Amp\Loop;

class MergeTest extends \PHPUnit_Framework_TestCase {
    public function getStreams() {
        return [
            [[Amp\stream(\range(1, 3)), Amp\stream(\range(4, 6))], [1, 4, 2, 5, 3, 6]],
            [[Amp\stream(\range(1, 5)), Amp\stream(\range(6, 8))], [1, 6, 2, 7, 3, 8, 4, 5]],
            [[Amp\stream(\range(1, 4)), Amp\stream(\range(5, 10))], [1, 5, 2, 6, 3, 7, 4, 8, 9, 10]],
        ];
    }

    /**
     * @dataProvider getStreams
     *
     * @param array $streams
     * @param array $expected
     */
    public function testMerge(array $streams, array $expected) {
        Loop::run(function () use ($streams, $expected) {
            $stream = Amp\merge($streams);

            Amp\each($stream, function ($value) use ($expected) {
                static $i = 0;
                $this->assertSame($expected[$i++], $value);
            });
        });
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
     * @expectedException \Error
     * @expectedExceptionMessage Non-stream provided
     */
    public function testNonStream() {
        Amp\merge([1]);
    }
}
