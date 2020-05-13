<?php

namespace Amp\Test\Iterator;

use Amp\Emitter;
use Amp\Iterator;
use Amp\Loop;
use Amp\PHPUnit\TestException;
use Amp\Producer;
use Amp\Test\BaseTest;

class MapTest extends BaseTest
{
    public function testNoValuesEmitted()
    {
        $invoked = false;
        Loop::run(function () use (&$invoked) {
            $emitter = new Emitter;

            $iterator = Iterator\map($emitter->iterate(), function ($value) use (&$invoked) {
                $invoked = true;
            });

            $this->assertInstanceOf(Iterator::class, $iterator);

            $emitter->complete();
        });

        $this->assertFalse($invoked);
    }

    public function testValuesEmitted()
    {
        Loop::run(function () {
            $count = 0;
            $values = [1, 2, 3];
            $producer = new Producer(function (callable $emit) use ($values) {
                foreach ($values as $value) {
                    yield $emit($value);
                }
            });

            $iterator = Iterator\map($producer, function ($value) use (&$count) {
                ++$count;
                return $value + 1;
            });

            while (yield $iterator->advance()) {
                $this->assertSame(\array_shift($values) + 1, $iterator->getCurrent());
            }

            $this->assertSame(3, $count);
        });
    }

    /**
     * @depends testValuesEmitted
     */
    public function testOnNextCallbackThrows()
    {
        Loop::run(function () {
            $values = [1, 2, 3];
            $exception = new TestException;

            $producer = new Producer(function (callable $emit) use ($values) {
                foreach ($values as $value) {
                    yield $emit($value);
                }
            });

            $iterator = Iterator\map($producer, function () use ($exception) {
                throw $exception;
            });

            try {
                yield $iterator->advance();
                $this->fail("The exception thrown from the map callback should be thrown from advance()");
            } catch (TestException $reason) {
                $this->assertSame($reason, $exception);
            }
        });
    }

    public function testIteratorFails()
    {
        Loop::run(function () {
            $invoked = false;
            $exception = new TestException;
            $emitter = new Emitter;

            $iterator = Iterator\map($emitter->iterate(), function ($value) use (&$invoked) {
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
        });
    }
}
