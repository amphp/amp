<?php

namespace Amp\Test;

use Amp\Deferred;
use Amp\Failure;
use Amp\Internal\Producer;
use Amp\PHPUnit\AsyncTestCase;
use Amp\PHPUnit\TestException;
use Amp\Promise;
use Amp\Success;
use function Amp\async;
use function Amp\await;

class InternalProducerTest extends AsyncTestCase
{
    private Producer $producer;

    public function setUp(): void
    {
        parent::setUp();

        $this->producer = new Producer;
    }

    public function testEmit(): void
    {
        $value = 1;

        $promise = async(fn () => $this->producer->emit($value));

        self::assertTrue(await($this->producer->advance()));
        self::assertSame($value, $this->producer->getCurrent());

        self::assertInstanceOf(Promise::class, $promise);
    }

    /**
     * @depends testEmit
     */
    public function testEmitSuccessfulPromise(): \Generator
    {
        $value = 1;
        $promise = new Success($value);

        $promise = $this->producer->emit($promise);

        self::assertTrue(yield $this->producer->advance());
        self::assertSame($value, $this->producer->getCurrent());

        self::assertInstanceOf(Promise::class, $promise);
    }

    /**
     * @depends testEmit
     */
    public function testEmitFailedPromise(): \Generator
    {
        $exception = new TestException;
        $promise = new Failure($exception);

        $promise = $this->producer->emit($promise);

        try {
            self::assertTrue(yield $this->producer->advance());
            self::fail("The exception used to fail the iterator should be thrown from advance()");
        } catch (TestException $reason) {
            self::assertSame($reason, $exception);
        }

        self::assertInstanceOf(Promise::class, $promise);
    }

    /**
     * @depends testEmit
     */
    public function testEmitPendingPromise(): void
    {
        $value = 1;
        $deferred = new Deferred;

        $this->producer->emit($deferred->promise());

        $deferred->resolve($value);

        self::assertTrue(await($this->producer->advance()));
        self::assertSame($value, $this->producer->getCurrent());
    }

    /**
     * @depends testEmit
     */
    public function testEmitPendingPromiseThenNonPromise(): void
    {
        $deferred = new Deferred;

        $this->producer->emit($deferred->promise());
        $this->producer->emit(2);

        self::assertTrue(await($this->producer->advance()));
        self::assertSame(2, $this->producer->getCurrent());

        $deferred->resolve(1);

        self::assertTrue(await($this->producer->advance()));
        self::assertSame(1, $this->producer->getCurrent());
    }

    /**
     * @depends testEmit
     */
    public function testEmitAfterComplete(): void
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage("Iterators cannot emit values after calling complete");

        $this->producer->complete();
        $this->producer->emit(1);
    }

    public function testGetCurrentAfterComplete(): void
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage("The iterator has completed");

        $this->producer->complete();
        $this->producer->getCurrent();
    }

    /**
     * @depends testEmit
     */
    public function testEmitPendingPromiseThenComplete(): void
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage("The iterator was completed before the promise result could be emitted");

        $deferred = new Deferred;

        $promise = $this->producer->emit($deferred->promise());

        $this->producer->complete();
        $deferred->resolve();

        await($promise);
    }

    /**
     * @depends testEmit
     */
    public function testEmitPendingPromiseThenFail(): void
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage("The iterator was completed before the promise result could be emitted");

        $deferred = new Deferred;

        $promise = $this->producer->emit($deferred->promise());

        $this->producer->complete();
        $deferred->fail(new \Exception);

        await($promise);
    }

    public function testDoubleAdvance(): void
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage("The prior promise returned must resolve before invoking this method again");

        $this->producer->advance();
        $this->producer->advance();
    }

    public function testGetCurrentBeforeAdvance(): void
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage("Promise returned from advance() must resolve before calling this method");

        $this->producer->getCurrent();
    }

    public function testDoubleComplete(): void
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage("Iterator has already been completed");

        $this->producer->complete();
        $this->producer->complete();
    }
}
