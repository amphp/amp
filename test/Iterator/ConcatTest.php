<?php

namespace Amp\Test\Iterator;

use Amp\Iterator;
use Amp\PHPUnit\AsyncTestCase;
use Amp\PHPUnit\TestException;
use Amp\Producer;

class ConcatTest extends AsyncTestCase
{
    public function getArrays(): array
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
    public function testConcat(array $iterators, array $expected): \Generator
    {
        $iterators = \array_map(function (array $iterator): Iterator {
            return Iterator\fromIterable($iterator);
        }, $iterators);

        $iterator = Iterator\concat($iterators);

        while (yield $iterator->advance()) {
            $this->assertSame(\array_shift($expected), $iterator->getCurrent());
        }
    }

    /**
     * @depends testConcat
     */
    public function testConcatWithFailedIterator(): \Generator
    {
        $exception = new TestException;
        $expected = \range(1, 6);
        $producer = new Producer(function (callable $emit) use ($exception) {
            yield $emit(6); // Emit once before failing.
            throw $exception;
        });

        $iterator = Iterator\concat([Iterator\fromIterable(\range(1, 5)), $producer, Iterator\fromIterable(\range(7, 10))]);

        try {
            while (yield $iterator->advance()) {
                $this->assertSame(\array_shift($expected), $iterator->getCurrent());
            }
            $this->fail("The exception used to fail the iterator should be thrown from advance()");
        } catch (TestException $reason) {
            $this->assertSame($exception, $reason);
        }

        $this->assertEmpty($expected);
    }

    public function testNonIterator(): void
    {
        $this->expectException(\TypeError::class);

        Iterator\concat([1]);
    }
}
