<?php

namespace Amp\Test\Stream;

use Amp\AsyncGenerator;
use Amp\Delayed;
use Amp\Loop;
use Amp\PHPUnit\TestException;
use Amp\Stream;
use Amp\Test\BaseTest;

class MergeTest extends BaseTest
{
    public function getArrays()
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
     * @param array $iterators
     * @param array $expected
     */
    public function testMerge(array $iterators, array $expected)
    {
        Loop::run(function () use ($iterators, $expected) {
            $iterators = \array_map(function (array $iterator): Stream {
                return Stream\fromIterable($iterator);
            }, $iterators);

            $iterator = Stream\merge($iterators);

            while (list($value) = yield $iterator->continue()) {
                $this->assertSame(\array_shift($expected), $value);
            }
        });
    }

    /**
     * @depends testMerge
     */
    public function testMergeWithDelayedYields()
    {
        Loop::run(function () {
            $iterators = [];
            $values1 = [new Delayed(10, 1), new Delayed(50, 2), new Delayed(70, 3)];
            $values2 = [new Delayed(20, 4), new Delayed(40, 5), new Delayed(60, 6)];
            $expected = [1, 4, 5, 2, 6, 3];

            $iterators[] = new AsyncGenerator(function (callable $yield) use ($values1) {
                foreach ($values1 as $value) {
                    yield $yield(yield $value);
                }
            });

            $iterators[] = new AsyncGenerator(function (callable $yield) use ($values2) {
                foreach ($values2 as $value) {
                    yield $yield(yield $value);
                }
            });

            $iterator = Stream\merge($iterators);

            while (list($value) = yield $iterator->continue()) {
                $this->assertSame(\array_shift($expected), $value);
            }
        });
    }

    /**
     * @depends testMerge
     */
    public function testMergeWithFailedStream()
    {
        Loop::run(function () {
            $exception = new TestException;
            $generator = new AsyncGenerator(function (callable $yield) use ($exception) {
                yield $yield(1); // Emit once before failing.
                throw $exception;
            });

            $iterator = Stream\merge([$generator, Stream\fromIterable(\range(1, 5))]);

            try {
                while (yield $iterator->continue()) ;
                $this->fail("The exception used to fail the iterator should be thrown from advance()");
            } catch (TestException $reason) {
                $this->assertSame($exception, $reason);
            }
        });
    }

    public function testNonStream()
    {
        $this->expectException(\TypeError::class);

        Stream\merge([1]);
    }
}
