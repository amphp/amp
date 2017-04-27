<?php

namespace Amp\Test;

use Amp;
use Amp\Deferred;
use Amp\Loop;
use Amp\Pause;
use Amp\Producer;
use PHPUnit\Framework\TestCase;
use React\Promise\Promise as ReactPromise;

class ProducerTest extends TestCase {
    const TIMEOUT = 100;

    /**
     * @expectedException \Error
     * @expectedExceptionMessage The callable did not return a Generator
     */
    public function testNonGeneratorCallable() {
        new Producer(function () {});
    }

    public function testEmit() {
        Loop::run(function () {
            $value = 1;

            $producer = new Producer(function (callable $emit) use ($value) {
                yield $emit($value);
            });

            $this->assertTrue(yield $producer->advance());
            $this->assertSame($producer->getCurrent(), $value);
        });
    }

    /**
     * @depends testEmit
     */
    public function testEmitSuccessfulPromise() {
        Loop::run(function () {
            $deferred = new Deferred();

            $producer = new Producer(function (callable $emit) use ($deferred) {
                yield $emit($deferred->promise());
            });

            $value = 1;
            $deferred->resolve($value);

            $this->assertTrue(yield $producer->advance());
            $this->assertSame($producer->getCurrent(), $value);
        });
    }

    /**
     * @depends testEmitSuccessfulPromise
     */
    public function testEmitFailedPromise() {
        $exception = new \Exception;
        Loop::run(function () use ($exception) {
            $deferred = new Deferred();

            $producer = new Producer(function (callable $emit) use ($deferred) {
                return yield $emit($deferred->promise());
            });

            $deferred->fail($exception);

            try {
                yield $producer->advance();
            } catch (\Exception $reason) {
                $this->assertSame($reason, $exception);
            }
        });
    }

    /**
     * @depends testEmit
     */
    public function testEmitBackPressure() {
        $emits = 3;
        Loop::run(function () use (&$time, $emits) {
            $producer = new Producer(function (callable $emit) use (&$time, $emits) {
                $time = microtime(true);
                for ($i = 0; $i < $emits; ++$i) {
                    yield $emit($i);
                }
                $time = microtime(true) - $time;
            });

            while (yield $producer->advance()) {
                yield new Pause(self::TIMEOUT);
            }
        });

        $this->assertGreaterThan(self::TIMEOUT * $emits - 1 /* 1ms grace period */, $time * 1000);
    }

    /**
     * @depends testEmit
     */
    public function testProducerCoroutineThrows() {
        $exception = new \Exception;

        try {
            Loop::run(function () use ($exception) {
                $producer = new Producer(function (callable $emit) use ($exception) {
                    yield $emit(1);
                    throw $exception;
                });

                while (yield $producer->advance());
            });
        } catch (\Exception $caught) {
            $this->assertSame($exception, $caught);
        }
    }
}
