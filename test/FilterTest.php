<?php

namespace Amp\Test;

use Amp\Emitter;
use Amp\Loop;
use Amp\Producer;
use Amp\Stream;

class FilterTest extends \PHPUnit\Framework\TestCase {
    public function testNoValuesEmitted() {
        $invoked = false;
        Loop::run(function () use (&$invoked) {
            $emitter = new Emitter;

            $stream = Stream\filter($emitter->stream(), function ($value) use (&$invoked) {
                $invoked = true;
            });

            $this->assertInstanceOf(Stream::class, $stream);

            $emitter->complete();
        });

        $this->assertFalse($invoked);
    }

    public function testValuesEmitted() {
        Loop::run(function () {
            $count = 0;
            $values = [1, 2, 3];
            $expected = [1, 3];
            $producer = new Producer(function (callable $emit) use ($values) {
                foreach ($values as $value) {
                    yield $emit($value);
                }
            });

            $stream = Stream\filter($producer, function ($value) use (&$count) {
                ++$count;
                return $value & 1;
            });

            while (yield $stream->advance()) {
                $this->assertSame(\array_shift($expected), $stream->getCurrent());
            }
            $this->assertSame(3, $count);
        });
    }

    /**
     * @depends testValuesEmitted
     */
    public function testCallbackThrows() {
        Loop::run(function () {
            $values = [1, 2, 3];
            $exception = new \Exception;
            $producer = new Producer(function (callable $emit) use ($values) {
                foreach ($values as $value) {
                    yield $emit($value);
                }
            });

            $stream = Stream\filter($producer, function () use ($exception) {
                throw $exception;
            });

            try {
                while (yield $stream->advance()) {
                    $stream->getCurrent();
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

            $stream = Stream\filter($emitter->stream(), function ($value) use (&$invoked) {
                $invoked = true;
            });

            $emitter->fail($exception);

            try {
                while (yield $stream->advance()) {
                    $stream->getCurrent();
                }
            } catch (\Exception $reason) {
                $this->assertSame($reason, $exception);
            }

            $this->assertFalse($invoked);
        });

    }
}
