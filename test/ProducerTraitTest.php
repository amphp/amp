<?php

namespace Amp\Test;

use Amp\Deferred;
use Amp\Failure;
use Amp\Loop;
use Amp\PHPUnit\TestException;
use Amp\Promise;
use Amp\Success;
use PHPUnit\Framework\TestCase;
use React\Promise\FulfilledPromise as FulfilledReactPromise;

class Producer {
    use \Amp\Internal\Producer {
        emit as public;
        complete as public;
        fail as public;
    }
}

class ProducerTraitTest extends TestCase {
    /** @var \Amp\Test\Producer */
    private $producer;

    public function setUp() {
        $this->producer = new Producer;
    }

    public function testEmit() {
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
    public function testEmitSuccessfulPromise() {
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
    public function testEmitFailedPromise() {
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
    public function testEmitPendingPromise() {
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
    public function testEmitSuccessfulReactPromise() {
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
    public function testEmitPendingPromiseThenNonPromise() {
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
     * @expectedException \Error
     * @expectedExceptionMessage Streams cannot emit values after calling complete
     */
    public function testEmitAfterComplete() {
        $this->producer->complete();
        $this->producer->emit(1);
    }

    /**
     * @depends testEmit
     * @expectedException \Error
     * @expectedExceptionMessage The stream was completed before the promise result could be emitted
     */
    public function testEmitPendingPromiseThenComplete() {
        $invoked = false;
        $deferred = new Deferred;

        $promise = $this->producer->emit($deferred->promise());

        $this->producer->complete();
        $deferred->resolve();

        $promise->onResolve(function ($exception) use (&$invoked, &$reason) {
            $invoked = true;
            $reason = $exception;
        });

        $this->assertTrue($invoked);
        throw $reason;
    }

    /**
     * @depends testEmit
     * @expectedException \Error
     * @expectedExceptionMessage The stream was completed before the promise result could be emitted
     */
    public function testEmitPendingPromiseThenFail() {
        $invoked = false;
        $deferred = new Deferred;

        $promise = $this->producer->emit($deferred->promise());

        $this->producer->complete();
        $deferred->fail(new \Exception);

        $promise->onResolve(function ($exception) use (&$invoked, &$reason) {
            $invoked = true;
            $reason = $exception;
        });

        $this->assertTrue($invoked);
        throw $reason;
    }
}
