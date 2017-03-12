<?php

namespace Amp\Test;

use Amp;
use Amp\Deferred;
use Amp\Producer;
use Amp\Pause;
use Amp\Loop;

class ProducerTest extends \PHPUnit\Framework\TestCase {
    const TIMEOUT = 100;

    /**
     * @expectedException \Error
     * @expectedExceptionMessage The callable did not return a Generator
     */
    public function testNonGeneratorCallable() {
        new Producer(function () {});
    }

    public function testEmit() {
        $invoked = false;
        Loop::run(Amp\wrap(function () use (&$invoked) {
            $value = 1;

            $producer = new Producer(function (callable $emit) use ($value) {
                yield $emit($value);
                return $value;
            });

            $invoked = false;
            $callback = function ($emitted) use (&$invoked, $value) {
                $invoked = true;
                $this->assertSame($emitted, $value);
            };

            $producer->listen($callback);

            $producer->when(function ($exception, $result) use ($value) {
                $this->assertSame($result, $value);
            });
        }));

        $this->assertTrue($invoked);
    }

    /**
     * @depends testEmit
     */
    public function testEmitSuccessfulPromise() {
        $invoked = false;
        Loop::run(function () use (&$invoked) {
            $deferred = new Deferred();

            $producer = new Producer(function (callable $emit) use ($deferred) {
                return yield $emit($deferred->promise());
            });

            $value = 1;
            $invoked = false;
            $callback = function ($emitted) use (&$invoked, $value) {
                $invoked = true;
                $this->assertSame($emitted, $value);
            };

            $producer->listen($callback);

            $deferred->resolve($value);
        });

        $this->assertTrue($invoked);
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

            $producer->when(function ($reason) use ($exception) {
                $this->assertSame($reason, $exception);
            });
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

            $producer->listen(function () {
                return new Pause(self::TIMEOUT);
            });
        });

        $this->assertGreaterThan(self::TIMEOUT * $emits, $time * 1000);
    }

    /**
     * @depends testEmit
     */
    public function testSubscriberThrows() {
        $exception = new \Exception;

        try {
            Loop::run(function () use ($exception) {
                $producer = new Producer(function (callable $emit) {
                    yield $emit(1);
                    yield $emit(2);
                });

                $producer->listen(function () use ($exception) {
                    throw $exception;
                });
            });
        } catch (\Exception $caught) {
            $this->assertSame($exception, $caught);
        }
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

                Amp\wait($producer);
            });
        } catch (\Exception $caught) {
            $this->assertSame($exception, $caught);
        }
    }
}
