<?php

namespace Amp\Test;

use Amp\Deferred;
use Amp\Failure;
use Amp\Loop;
use Amp\Success;
use Amp\Promise;
use PHPUnit\Framework\TestCase;
use React\Promise\FulfilledPromise as FulfilledReactPromise;

class Producer {
    use \Amp\Internal\Producer {
        emit as public;
        resolve as public;
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
        $invoked = false;
        $value = 1;

        $callback = function ($emitted) use (&$invoked, $value) {
            $invoked = true;
            $this->assertSame($emitted, $value);
        };

        $this->producer->onEmit($callback);
        $promise = $this->producer->emit($value);

        $this->assertInstanceOf(Promise::class, $promise);
        $this->assertTrue($invoked);
    }

    /**
     * @depends testEmit
     */
    public function testEmitSuccessfulPromise() {
        $invoked = false;
        $value = 1;
        $promise = new Success($value);

        $callback = function ($emitted) use (&$invoked, $value) {
            $invoked = true;
            $this->assertSame($emitted, $value);
        };

        $this->producer->onEmit($callback);
        $this->producer->emit($promise);

        $this->assertTrue($invoked);
    }

    /**
     * @depends testEmit
     */
    public function testEmitFailedPromise() {
        $invoked = false;
        $exception = new \Exception;
        $promise = new Failure($exception);

        $callback = function ($emitted) use (&$invoked) {
            $invoked = true;
        };

        $this->producer->onEmit($callback);
        $this->producer->emit($promise);

        $this->assertFalse($invoked);

        $this->producer->onResolve(function ($exception) use (&$invoked, &$reason) {
            $invoked = true;
            $reason = $exception;
        });

        $this->assertTrue($invoked);
        $this->assertSame($exception, $reason);
    }

    /**
     * @depends testEmit
     */
    public function testEmitPendingPromise() {
        $invoked = false;
        $value = 1;
        $deferred = new Deferred;

        $callback = function ($emitted) use (&$invoked, $value) {
            $invoked = true;
            $this->assertSame($emitted, $value);
        };

        $this->producer->onEmit($callback);
        $this->producer->emit($deferred->promise());

        $this->assertFalse($invoked);

        $deferred->resolve($value);

        $this->assertTrue($invoked);
    }

    /**
     * @depends testEmit
     */
    public function testEmitSuccessfulReactPromise() {
        $invoked = false;
        $value = 1;
        $promise = new FulfilledReactPromise($value);

        $callback = function ($emitted) use (&$invoked, $value) {
            $invoked = true;
            $this->assertSame($emitted, $value);
        };

        $this->producer->onEmit($callback);
        $this->producer->emit($promise);

        $this->assertTrue($invoked);
    }

    /**
     * @depends testEmit
     */
    public function testEmitPendingPromiseThenNonPromise() {
        $invoked = false;
        $deferred = new Deferred;

        $callback = function ($emitted) use (&$invoked, &$result) {
            $invoked = true;
            $result = $emitted;
        };

        $this->producer->onEmit($callback);
        $this->producer->emit($deferred->promise());

        $this->assertFalse($invoked);

        $this->producer->emit(2);
        $this->assertTrue($invoked);
        $this->assertSame(2, $result);

        $deferred->resolve(1);
        $this->assertSame(1, $result);
    }

    /**
     * @depends testEmit
     * @expectedException \Error
     * @expectedExceptionMessage Streams cannot emit values after calling resolve
     */
    public function testEmitAfterResolve() {
        $this->producer->resolve();
        $this->producer->emit(1);
    }

    /**
     * @depends testEmit
     * @expectedException \Error
     * @expectedExceptionMessage The stream was resolved before the promise result could be emitted
     */
    public function testEmitPendingPromiseThenResolve() {
        $invoked = false;
        $deferred = new Deferred;

        $promise = $this->producer->emit($deferred->promise());

        $this->producer->resolve();
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
     * @expectedExceptionMessage The stream was resolved before the promise result could be emitted
     */
    public function testEmitPendingPromiseThenFail() {
        $invoked = false;
        $deferred = new Deferred;

        $promise = $this->producer->emit($deferred->promise());

        $this->producer->resolve();
        $deferred->fail(new \Exception);

        $promise->onResolve(function ($exception) use (&$invoked, &$reason) {
            $invoked = true;
            $reason = $exception;
        });

        $this->assertTrue($invoked);
        throw $reason;
    }

    public function testSubscriberThrows() {
        $exception = new \Exception;

        try {
            Loop::run(function () use ($exception) {
                $this->producer->onEmit(function () use ($exception) {
                    throw $exception;
                });

                $this->producer->emit(1);
            });
        } catch (\Exception $caught) {
            $this->assertSame($exception, $caught);
        }
    }

    public function testSubscriberReturnsSuccessfulPromise() {
        $invoked = true;
        $value = 1;
        $promise = new Success($value);

        $this->producer->onEmit(function () use ($promise) {
            return $promise;
        });

        $promise = $this->producer->emit(1);
        $promise->onResolve(function () use (&$invoked) {
            $invoked = true;
        });

        $this->assertTrue($invoked);
    }

    public function testSubscriberReturnsFailedPromise() {
        $exception = new \Exception;
        $promise = new Failure($exception);

        try {
            Loop::run(function () use ($exception, $promise) {
                $this->producer->onEmit(function () use ($promise) {
                    return $promise;
                });

                $promise = $this->producer->emit(1);
                $promise->onResolve(function () use (&$invoked) {
                    $invoked = true;
                });

                $this->assertTrue($invoked);
            });
        } catch (\Exception $caught) {
            $this->assertSame($exception, $caught);
        }
    }
}
