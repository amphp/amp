<?php

namespace Amp\Test\Iterator;

use Amp\Emitter;
use Amp\Iterator;
use Amp\PHPUnit\AsyncTestCase;
use Amp\PHPUnit\TestException;
use Amp\Producer;

class FilterTest extends AsyncTestCase
{
    public function testNoValuesEmitted(): \Generator
    {
        $invoked = false;
        $emitter = new Emitter;

        $iterator = Iterator\filter($emitter->iterate(), function ($value) use (&$invoked) {
            $invoked = true;
        });

        $this->assertInstanceOf(Iterator::class, $iterator);

        $emitter->complete();

        $this->assertFalse($invoked);
    }

    public function testValuesEmitted(): \Generator
    {
        $count = 0;
        $values = [1, 2, 3];
        $expected = [1, 3];
        $producer = new Producer(function (callable $emit) use ($values) {
            foreach ($values as $value) {
                yield $emit($value);
            }
        });

        $iterator = Iterator\filter($producer, function ($value) use (&$count) {
            ++$count;
            return $value & 1;
        });

        while (yield $iterator->advance()) {
            $this->assertSame(\array_shift($expected), $iterator->getCurrent());
        }
        $this->assertSame(3, $count);
    }

    /**
     * @depends testValuesEmitted
     */
    public function testCallbackThrows(): \Generator
    {
        $values = [1, 2, 3];
        $exception = new TestException;
        $producer = new Producer(function (callable $emit) use ($values) {
            foreach ($values as $value) {
                yield $emit($value);
            }
        });

        $iterator = Iterator\filter($producer, function () use ($exception) {
            throw $exception;
        });

        try {
            yield $iterator->advance();
            $this->fail("The exception thrown from the filter callback should be thrown from advance()");
        } catch (TestException $reason) {
            $this->assertSame($reason, $exception);
        }
    }

    public function testIteratorFails(): \Generator
    {
        $invoked = false;
        $exception = new TestException;
        $emitter = new Emitter;

        $iterator = Iterator\filter($emitter->iterate(), function ($value) use (&$invoked) {
            $invoked = true;
        });

        $emitter->fail($exception);

        try {
            yield $iterator->advance();
            $this->fail("The exception used to fail the iterator should be thrown from advance()");
        } catch (TestException $reason) {
            $this->assertSame($reason, $exception);
        }

        $this->assertFalse($invoked);
    }
}
