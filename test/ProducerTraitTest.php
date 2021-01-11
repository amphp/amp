<?php

namespace Amp\Test;

use Amp\Deferred;
use Amp\Failure;
use Amp\Loop;
use Amp\PHPUnit\TestException;
use Amp\Promise;
use Amp\Success;
use React\Promise\FulfilledPromise as FulfilledReactPromise;

class Producer
{
    use \Amp\Internal\Producer {
        emit as public;
        complete as public;
        fail as public;
    }
}

class ProducerTraitTest extends BaseTest
{
    /** @var \Amp\Test\Producer */
    private $producer;

    public function setUp(): void
    {
        $this->producer = new Producer;
    }

    public function testEmit(): void
    {
        Loop::run(function () {
            $value = 1;

            $promise = $this->producer->emit($value);

            $this->assertTrue(yield $this->producer->advance());
            $this->assertSame($value, $this->producer->getCurrent());

            $this->assertInstanceOf(Promise::class, $promise);
        });
    }

    /**
     * @depends testEmit
     */
    public function testEmitSuccessfulPromise(): void
    {
        Loop::run(function () {
            $value = 1;
            $promise = new Success($value);

            $promise = $this->producer->emit($promise);

            $this->assertTrue(yield $this->producer->advance());
            $this->assertSame($value, $this->producer->getCurrent());

            $this->assertInstanceOf(Promise::class, $promise);
        });
    }

    /**
     * @depends testEmit
     */
    public function testEmitFailedPromise(): void
    {
        Loop::run(function () {
            $exception = new TestException;
            $promise = new Failure($exception);

            $promise = $this->producer->emit($promise);

            try {
                $this->assertTrue(yield $this->producer->advance());
                $this->fail("The exception used to fail the iterator should be thrown from advance()");
            } catch (TestException $reason) {
                $this->assertSame($reason, $exception);
            }

            $this->assertInstanceOf(Promise::class, $promise);
        });
    }

    /**
     * @depends testEmit
     */
    public function testEmitPendingPromise(): void
    {
        Loop::run(function () {
            $value = 1;
            $deferred = new Deferred;

            $this->producer->emit($deferred->promise());

            $deferred->resolve($value);

            $this->assertTrue(yield $this->producer->advance());
            $this->assertSame($value, $this->producer->getCurrent());
        });
    }

    /**
     * @depends testEmit
     */
    public function testEmitSuccessfulReactPromise(): void
    {
        Loop::run(function () {
            $value = 1;
            $promise = new FulfilledReactPromise($value);

            $this->producer->emit($promise);

            $this->assertTrue(yield $this->producer->advance());
            $this->assertSame($value, $this->producer->getCurrent());
        });
    }

    /**
     * @depends testEmit
     */
    public function testEmitPendingPromiseThenNonPromise(): void
    {
        Loop::run(function () {
            $deferred = new Deferred;

            $this->producer->emit($deferred->promise());

            $this->producer->emit(2);

            $this->assertTrue(yield $this->producer->advance());
            $this->assertSame(2, $this->producer->getCurrent());

            $deferred->resolve(1);

            $this->assertTrue(yield $this->producer->advance());
            $this->assertSame(1, $this->producer->getCurrent());
        });
    }

    /**
     * @depends testEmit
     */
    public function testEmitAfterComplete(): void
    {
        $this->producer->complete();

        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Iterators cannot emit values after calling complete');

        $this->producer->emit(1);
    }

    public function testGetCurrentAfterComplete(): void
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('The iterator has completed');

        $this->producer->complete();
        $this->producer->getCurrent();
    }

    /**
     * @depends testEmit
     */
    public function testEmitPendingPromiseThenComplete(): void
    {
        $invoked = false;
        $deferred = new Deferred;

        $promise = $this->producer->emit($deferred->promise());

        $this->producer->complete();
        $deferred->resolve();

        $promise->onResolve(function ($exception) use (&$invoked, &$reason) {
            $invoked = true;
            $reason = $exception;
        });

        self::assertTrue($invoked);

        $this->expectException(\Error::class);
        $this->expectExceptionMessage('The iterator was completed before the promise result could be emitted');

        throw $reason;
    }

    /**
     * @depends testEmit
     */
    public function testEmitPendingPromiseThenFail(): void
    {
        $invoked = false;
        $deferred = new Deferred;

        $promise = $this->producer->emit($deferred->promise());

        $this->producer->complete();
        $deferred->fail(new \Exception);

        $promise->onResolve(function ($exception) use (&$invoked, &$reason) {
            $invoked = true;
            $reason = $exception;
        });

        self::assertTrue($invoked);

        $this->expectException(\Error::class);
        $this->expectExceptionMessage('The iterator was completed before the promise result could be emitted');

        throw $reason;
    }

    public function testDoubleAdvance(): void
    {
        $this->producer->advance();

        $this->expectException(\Error::class);
        $this->expectExceptionMessage('The prior promise returned must resolve before invoking this method again');

        $this->producer->advance();
    }

    public function testGetCurrentBeforeAdvance(): void
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Promise returned from advance() must resolve before calling this method');

        $this->producer->getCurrent();
    }

    public function testDoubleComplete(): void
    {
        $this->producer->complete();

        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Iterator has already been completed');

        $this->producer->complete();
    }
}
