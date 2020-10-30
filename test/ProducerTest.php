<?php

namespace Amp\Test;

use Amp\Deferred;
use Amp\PHPUnit\AsyncTestCase;
use Amp\PHPUnit\TestException;
use Amp\Producer;
use function Amp\await;
use function Amp\delay;

class ProducerTest extends AsyncTestCase
{
    const TIMEOUT = 100;

    public function testNonGeneratorCallable(): void
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage("The callable did not return a Generator");

        new Producer(function () {
        });
    }

    public function testEmit(): void
    {
        $value = 1;

        $producer = new Producer(function (callable $emit) use ($value) {
            yield $emit($value);
        });

        $this->assertTrue(await($producer->advance()));
        $this->assertSame($producer->getCurrent(), $value);
    }

    /**
     * @depends testEmit
     */
    public function testEmitSuccessfulPromise(): void
    {
        $deferred = new Deferred();

        $producer = new Producer(function (callable $emit) use ($deferred) {
            yield $emit($deferred->promise());
        });

        $value = 1;
        $deferred->resolve($value);

        $this->assertTrue(await($producer->advance()));
        $this->assertSame($producer->getCurrent(), $value);
    }

    /**
     * @depends testEmitSuccessfulPromise
     */
    public function testEmitFailedPromise(): void
    {
        $exception = new TestException;
        $deferred = new Deferred();

        $producer = new Producer(function (callable $emit) use ($deferred) {
            return yield $emit($deferred->promise());
        });

        $deferred->fail($exception);

        try {
            await($producer->advance());
            $this->fail("Emitting a failed promise should fail the iterator");
        } catch (TestException $reason) {
            $this->assertSame($reason, $exception);
        }
}

    /**
     * @depends testEmit
     */
    public function testEmitBackPressure(): void
    {
        $emits = 3;
        $producer = new Producer(function (callable $emit) use (&$time, $emits) {
            $time = \microtime(true);
            for ($i = 0; $i < $emits; ++$i) {
                yield $emit($i);
            }
            $time = \microtime(true) - $time;
        });

        while (await($producer->advance())) {
            delay(self::TIMEOUT);
        }

        $this->assertGreaterThan(self::TIMEOUT * ($emits - 1), $time * 1000);
    }

    /**
     * @depends testEmit
     */
    public function testProducerCoroutineThrows(): void
    {
        $exception = new TestException;

        try {
            $producer = new Producer(function (callable $emit) use ($exception) {
                yield $emit(1);
                throw $exception;
            });

            while (await($producer->advance())) ;
            $this->fail("The exception thrown from the coroutine should fail the iterator");
        } catch (TestException $caught) {
            $this->assertSame($exception, $caught);
        }
    }
}
