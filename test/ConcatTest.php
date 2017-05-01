<?php

namespace Amp\Test;

use Amp\Iterator;
use Amp\Loop;
use Amp\PHPUnit\TestException;
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
     * @param array $iterators
     * @param array $expected
     */
    public function testConcat(array $iterators, array $expected) {
        Loop::run(function () use ($iterators, $expected) {
            $iterators = \array_map(function (array $iterator): Iterator {
                return Iterator\fromIterable($iterator);
            }, $iterators);

            $iterator = Iterator\concat($iterators);

            while (yield $iterator->advance()) {
                $this->assertSame(\array_shift($expected), $iterator->getCurrent());
            }
        });
    }

    /**
     * @depends testConcat
     */
    public function testConcatWithFailedIterator() {
        Loop::run(function () {
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
        });
    }

    /**
     * @expectedException \TypeError
     */
    public function testNonIterator() {
        Iterator\concat([1]);
    }
}
