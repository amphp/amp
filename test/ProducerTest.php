<?php

namespace Amp\Test;

use Amp\Deferred;
use Amp\Delayed;
use Amp\Loop;
use Amp\PHPUnit\TestException;
use Amp\Producer;

class ProducerTest extends BaseTest
{
    private const TIMEOUT = 100;

    public function testNonGeneratorCallable(): void
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('The callable did not return a Generator');

        new Producer(function () {
        });
    }

    public function testEmit(): void
    {
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
    public function testEmitSuccessfulPromise(): void
    {
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
    public function testEmitFailedPromise(): void
    {
        $exception = new TestException;
        Loop::run(function () use ($exception) {
            $deferred = new Deferred();

            $producer = new Producer(function (callable $emit) use ($deferred) {
                return yield $emit($deferred->promise());
            });

            $deferred->fail($exception);

            try {
                yield $producer->advance();
                $this->fail("Emitting a failed promise should fail the iterator");
            } catch (TestException $reason) {
                $this->assertSame($reason, $exception);
            }
        });
    }

    /**
     * @depends testEmit
     */
    public function testEmitBackPressure(): void
    {
        $emits = 3;
        Loop::run(function () use (&$time, $emits) {
            $producer = new Producer(function (callable $emit) use (&$time, $emits) {
                $time = \microtime(true);
                for ($i = 0; $i < $emits; ++$i) {
                    yield $emit($i);
                }
                $time = \microtime(true) - $time;
            });

            while (yield $producer->advance()) {
                yield new Delayed(self::TIMEOUT);
            }
        });

        $this->assertGreaterThan(self::TIMEOUT * ($emits - 1), $time * 1000 + 1);
    }

    /**
     * @depends testEmit
     */
    public function testProducerCoroutineThrows(): void
    {
        $exception = new TestException;

        try {
            Loop::run(function () use ($exception) {
                $producer = new Producer(function (callable $emit) use ($exception) {
                    yield $emit(1);
                    throw $exception;
                });

                while (yield $producer->advance()) {
                    ;
                }
                $this->fail("The exception thrown from the coroutine should fail the iterator");
            });
        } catch (TestException $caught) {
            $this->assertSame($exception, $caught);
        }
    }
}
