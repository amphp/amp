<?php

namespace Amp\Test\Stream;

use Amp\AsyncGenerator;
use Amp\Loop;
use Amp\PHPUnit\TestException;
use Amp\Stream;
use Amp\Test\BaseTest;

class ConcatTest extends BaseTest
{
    public function getArrays()
    {
        return [
            [[\range(1, 3), \range(4, 6)], \range(1, 6)],
            [[\range(1, 5), \range(6, 8)], \range(1, 8)],
            [[\range(1, 4), \range(5, 10)], \range(1, 10)],
        ];
    }

    /**
     * @dataProvider getArrays
     *
     * @param array $iterators
     * @param array $expected
     */
    public function testConcat(array $iterators, array $expected)
    {
        Loop::run(function () use ($iterators, $expected) {
            $iterators = \array_map(function (array $iterator): Stream {
                return Stream\fromIterable($iterator);
            }, $iterators);

            $iterator = Stream\concat($iterators);

            while (list($value) = yield $iterator->continue()) {
                $this->assertSame(\array_shift($expected), $value);
            }
        });
    }

    /**
     * @depends testConcat
     */
    public function testConcatWithFailedStream()
    {
        Loop::run(function () {
            $exception = new TestException;
            $expected = \range(1, 6);
            $generator = new AsyncGenerator(function (callable $yield) use ($exception) {
                yield $yield(6); // Emit once before failing.
                throw $exception;
            });

            $iterator = Stream\concat([Stream\fromIterable(\range(1, 5)), $generator, Stream\fromIterable(\range(7, 10))]);

            try {
                while (list($value) = yield $iterator->continue()) {
                    $this->assertSame(\array_shift($expected), $value);
                }
                $this->fail("The exception used to fail the iterator should be thrown from advance()");
            } catch (TestException $reason) {
                $this->assertSame($exception, $reason);
            }

            $this->assertEmpty($expected);
        });
    }

    public function testNonStream()
    {
        $this->expectException(\TypeError::class);

        Stream\concat([1]);
    }
}
