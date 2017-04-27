<?php

namespace Amp\Test;

use Amp\Emitter;
use Amp\Iterator;
use Amp\Loop;
use Amp\Producer;

class MapTest extends \PHPUnit\Framework\TestCase {
    public function testNoValuesEmitted() {
        $invoked = false;
        Loop::run(function () use (&$invoked) {
            $emitter = new Emitter;

            $iterator = Iterator\map($emitter->getIterator(), function ($value) use (&$invoked) {
                $invoked = true;
            });

            $this->assertInstanceOf(Iterator::class, $iterator);

            $emitter->complete();
        });

        $this->assertFalse($invoked);
    }

    public function testValuesEmitted() {
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
    public function testOnNextCallbackThrows() {
        Loop::run(function () {
            $values = [1, 2, 3];
            $exception = new \Exception;

            $producer = new Producer(function (callable $emit) use ($values) {
                foreach ($values as $value) {
                    yield $emit($value);
                }
            });

            $iterator = Iterator\map($producer, function () use ($exception) {
                throw $exception;
            });

            try {
                while (yield $iterator->advance()) {
                    $iterator->getCurrent();
                }
            } catch (\Exception $reason) {
                $this->assertSame($reason, $exception);
            }
        });
    }

    public function testStreamFails() {
        Loop::run(function () {
            $invoked = false;
            $exception = new \Exception;
            $emitter = new Emitter;

            $iterator = Iterator\map($emitter->getIterator(), function ($value) use (&$invoked) {
                $invoked = true;
            });

            $emitter->fail($exception);

            try {
                while (yield $iterator->advance()) {
                    $iterator->getCurrent();
                }
            } catch (\Exception $reason) {
                $this->assertSame($reason, $exception);
            }

            $this->assertFalse($invoked);
        });
    }
}
