<?php

namespace Amp\Test;

use Amp;
use Amp\Producer;
use Amp\StreamIterator;
use Amp\Pause;
use Amp\Emitter;
use Amp\Loop;
use PHPUnit\Framework\TestCase;

class StreamIteratorTest extends TestCase {
    const TIMEOUT = 10;

    public function testSingleEmittingStream() {
        Loop::run(function () {
            $value = 1;
            $stream = new Producer(function (callable $emit) use ($value) {
                yield $emit($value);
                return $value;
            });

            $streamIterator = new StreamIterator($stream);

            while (yield $streamIterator->advance()) {
                $this->assertSame($streamIterator->getCurrent(), $value);
            }

            $this->assertSame($streamIterator->getResult(), $value);
        });
    }

    /**
     * @depends testSingleEmittingStream
     */
    public function testFastEmittingStream() {
        Loop::run(function () {
            $count = 10;

            $emitter = new Emitter;

            $streamIterator = new StreamIterator($emitter->stream());

            for ($i = 0; $i < $count; ++$i) {
                $promises[] = $emitter->emit($i);
            }

            $emitter->resolve($i);

            for ($i = 0; yield $streamIterator->advance(); ++$i) {
                $this->assertSame($streamIterator->getCurrent(), $i);
            }

            $this->assertSame($count, $i);
            $this->assertSame($streamIterator->getResult(), $i);
        });
    }

    /**
     * @depends testSingleEmittingStream
     */
    public function testSlowEmittingStream() {
        Loop::run(function () {
            $count = 10;
            $stream = new Producer(function (callable $emit) use ($count) {
                for ($i = 0; $i < $count; ++$i) {
                    yield new Pause(self::TIMEOUT);
                    yield $emit($i);
                }
                return $i;
            });

            $streamIterator = new StreamIterator($stream);

            for ($i = 0; yield $streamIterator->advance(); ++$i) {
                $this->assertSame($streamIterator->getCurrent(), $i);
            }

            $this->assertSame($count, $i);
            $this->assertSame($streamIterator->getResult(), $i);
        });
    }

    /**
     * @depends testFastEmittingStream
     */
    public function testDrain() {
        Loop::run(function () {
            $count = 10;
            $expected = \range(0, $count - 1);

            $emitter = new Emitter;

            $streamIterator = new StreamIterator($emitter->stream());

            for ($i = 0; $i < $count; ++$i) {
                $promises[] = $emitter->emit($i);
            }

            $value = null;
            if (yield $streamIterator->advance()) {
                $value = $streamIterator->getCurrent();
            }

            $this->assertSame(reset($expected), $value);
            unset($expected[0]);

            $emitter->resolve($i);

            $values = $streamIterator->drain();

            $this->assertSame($expected, $values);
        });
    }

    /**
     * @expectedException \Error
     * @expectedExceptionMessage The stream has not resolved
     */
    public function testDrainBeforeResolution() {
        $emitter = new Emitter;

        $streamIterator = new StreamIterator($emitter->stream());

        $streamIterator->drain();
    }

    public function testFailingStream() {
        Loop::run(function () {
            $exception = new \Exception;

            $emitter = new Emitter;

            $streamIterator = new StreamIterator($emitter->stream());

            $emitter->fail($exception);

            try {
                while (yield $streamIterator->advance());
                $this->fail("StreamIterator::advance() should throw stream failure reason");
            } catch (\Exception $reason) {
                $this->assertSame($exception, $reason);
            }

            try {
                $streamIterator->getResult();
                $this->fail("StreamIterator::getResult() should throw stream failure reason");
            } catch (\Exception $reason) {
                $this->assertSame($exception, $reason);
            }
        });
    }

    /**
     * @expectedException \Error
     * @expectedExceptionMessage Promise returned from advance() must resolve before calling this method
     */
    public function testGetCurrentBeforeAdvanceResolves() {
        $streamIterator = new StreamIterator((new Emitter)->stream());
        $streamIterator->advance();
        $streamIterator->getCurrent();
    }

    /**
     * @expectedException \Error
     * @expectedExceptionMessage The stream has resolved
     */
    public function testGetCurrentAfterResolution() {
        $emitter = new Emitter;
        $streamIterator = new StreamIterator($emitter->stream());

        $emitter->resolve();
        $streamIterator->getCurrent();
    }

    /**
     * @expectedException \Error
     * @expectedExceptionMessage The stream has not resolved
     */
    public function testGetResultBeforeResolution() {
        Loop::run(Amp\wrap(function () {
            $streamIterator = new StreamIterator((new Emitter)->stream());
            $streamIterator->getResult();
        }));
    }

    /**
     * @expectedException \Error
     * @expectedExceptionMessage The prior promise returned must resolve before invoking this method again
     */
    public function testConsecutiveAdvanceCalls() {
        $emitter = new Emitter;
        $streamIterator = new StreamIterator($emitter->stream());
        $streamIterator->advance();
        $streamIterator->advance();
    }

    public function testStreamIteratorDestroyedAfterEmits() {
        $emitter = new Emitter;
        $streamIterator = new StreamIterator($emitter->stream());

        $promise = $emitter->emit(1);

        unset($streamIterator);

        $invoked = false;
        $promise->onResolve(function () use (&$invoked) {
            $invoked = true;
        });

        $this->assertTrue($invoked);
    }

    public function testStreamIteratorDestroyedThenStreamEmits() {
        $emitter = new Emitter;
        $streamIterator = new StreamIterator($emitter->stream());

        $emitter->emit(1);

        unset($streamIterator);

        $promise = $emitter->emit(2);

        $invoked = false;
        $promise->onResolve(function () use (&$invoked) {
            $invoked = true;
        });

        $this->assertTrue($invoked);
    }

    public function testStreamFailsWhenStreamIteratorWaiting() {
        $exception = new \Exception;
        $emitter = new Emitter;
        $streamIterator = new StreamIterator($emitter->stream());

        $promise = $streamIterator->advance();
        $promise->onResolve(function ($exception, $value) use (&$reason) {
            $reason = $exception;
        });

        $emitter->fail($exception);

        $this->assertSame($exception, $reason);
    }
}
