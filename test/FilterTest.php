<?php

namespace Amp\Test;

use Amp\Producer;
use Amp\Stream;
use Amp\Emitter;
use Amp\Loop;

class FilterTest extends \PHPUnit\Framework\TestCase {
    public function testNoValuesEmitted() {
        $invoked = false;
        Loop::run(function () use (&$invoked){
            $emitter = new Emitter;

            $stream = Stream\filter($emitter->stream(), function ($value) use (&$invoked) {
                $invoked = true;
            });

            $this->assertInstanceOf(Stream::class, $stream);

            $emitter->resolve();
        });

        $this->assertFalse($invoked);
    }

    public function testValuesEmitted() {
        $count = 0;
        $values = [1, 2, 3];
        $results = [];
        $expected = [1, 3];
        Loop::run(function () use (&$results, &$result, &$count, $values) {
            $producer = new Producer(function (callable $emit) use ($values) {
                foreach ($values as $value) {
                    yield $emit($value);
                }
            });

            $stream = Stream\filter($producer, function ($value) use (&$count) {
                ++$count;
                return $value & 1;
            });

            $stream->listen(function ($value) use (&$results) {
                $results[] = $value;
            });

            $stream->onResolve(function ($exception, $value) use (&$result) {
                $result = $value;
            });
        });

        $this->assertSame(\count($values), $count);
        $this->assertSame($expected, $results);
    }

    /**
     * @depends testValuesEmitted
     */
    public function testCallbackThrows() {
        $values = [1, 2, 3];
        $exception = new \Exception;
        Loop::run(function () use (&$reason, $values, $exception) {
            $producer = new Producer(function (callable $emit) use ($values) {
                foreach ($values as $value) {
                    yield $emit($value);
                }
            });

            $stream = Stream\filter($producer, function () use ($exception) {
                throw $exception;
            });

            $stream->listen(function ($value) use (&$results) {
                $results[] = $value;
            });

            $callback = function ($exception, $value) use (&$reason) {
                $reason = $exception;
            };

            $stream->onResolve($callback);
        });

        $this->assertSame($exception, $reason);
    }

    public function testStreamFails() {
        $invoked = false;
        $exception = new \Exception;
        Loop::run(function () use (&$invoked, &$reason, &$exception){
            $emitter = new Emitter;

            $stream = Stream\filter($emitter->stream(), function ($value) use (&$invoked) {
                $invoked = true;
            });

            $emitter->fail($exception);

            $callback = function ($exception, $value) use (&$reason) {
                $reason = $exception;
            };

            $stream->onResolve($callback);
        });

        $this->assertFalse($invoked);
        $this->assertSame($exception, $reason);
    }
}
