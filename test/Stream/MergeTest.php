<?php

namespace Amp\Test\Stream;

use Amp\AsyncGenerator;
use Amp\Delayed;
use Amp\PHPUnit\AsyncTestCase;
use Amp\PHPUnit\TestException;
use Amp\Stream;

class MergeTest extends AsyncTestCase
{
    public function getArrays(): array
    {
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
    public function testMerge(array $streams, array $expected)
    {
        $streams = \array_map(static function (array $iterator): Stream {
            return Stream\fromIterable($iterator);
        }, $streams);

        $stream = Stream\merge($streams);

        while ($value = yield $stream->continue()) {
            $this->assertSame(\array_shift($expected), $value->unwrap());
        }
    }

    /**
     * @depends testMerge
     */
    public function testMergeWithDelayedYields()
    {
        $streams = [];
        $values1 = [new Delayed(10, 1), new Delayed(50, 2), new Delayed(70, 3)];
        $values2 = [new Delayed(20, 4), new Delayed(40, 5), new Delayed(60, 6)];
        $expected = [1, 4, 5, 2, 6, 3];

        $streams[] = new AsyncGenerator(function (callable $yield) use ($values1) {
            foreach ($values1 as $value) {
                yield $yield(yield $value);
            }
        });

        $streams[] = new AsyncGenerator(function (callable $yield) use ($values2) {
            foreach ($values2 as $value) {
                yield $yield(yield $value);
            }
        });

        $stream = Stream\merge($streams);

        while ($value = yield $stream->continue()) {
            $this->assertSame(\array_shift($expected), $value->unwrap());
        }
    }

    /**
     * @depends testMerge
     */
    public function testMergeWithFailedStream()
    {
        $exception = new TestException;
        $generator = new AsyncGenerator(static function (callable $yield) use ($exception) {
            yield $yield(1); // Emit once before failing.
            throw $exception;
        });

        $stream = Stream\merge([$generator, Stream\fromIterable(\range(1, 5))]);

        try {
            /** @noinspection PhpStatementHasEmptyBodyInspection */
            while (yield $stream->continue()) {
                ;
            }
            $this->fail("The exception used to fail the stream should be thrown from continue()");
        } catch (TestException $reason) {
            $this->assertSame($exception, $reason);
        }
    }

    public function testNonStream()
    {
        $this->expectException(\TypeError::class);

        /** @noinspection PhpParamsInspection */
        Stream\merge([1]);
    }
}
