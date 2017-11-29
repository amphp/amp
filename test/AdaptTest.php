<?php

namespace Amp\Test;

use Amp\Failure;
use Amp\Promise;
use Amp\Success;

class PromiseMock {
    /** @var \Amp\Promise */
    private $promise;

    public function __construct(Promise $promise) {
        $this->promise = $promise;
    }

    public function then(callable $onFulfilled = null, callable $onRejected = null) {
        $this->promise->onResolve(function ($exception, $value) use ($onFulfilled, $onRejected) {
            if ($exception) {
                if ($onRejected) {
                    $onRejected($exception);
                }
                return;
            }

            if ($onFulfilled) {
                $onFulfilled($value);
            }
        });
    }
}

class AdaptTest extends \PHPUnit\Framework\TestCase {
    public function testThenCalled() {
        $mock = $this->getMockBuilder(PromiseMock::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mock->expects($this->once())
            ->method("then")
            ->with(
                $this->callback(function ($resolve) {
                    return \is_callable($resolve);
                }),
                $this->callback(function ($reject) {
                    return \is_callable($reject);
                })
            );

        $promise = Promise\adapt($mock);

        $this->assertInstanceOf(Promise::class, $promise);
    }

    /**
     * @depends testThenCalled
     */
    public function testPromiseFulfilled() {
        $value = 1;

        $promise = new PromiseMock(new Success($value));

        $promise = Promise\adapt($promise);

        $promise->onResolve(function ($exception, $value) use (&$result) {
            $result = $value;
        });

        $this->assertSame($value, $result);
    }

    /**
     * @depends testThenCalled
     */
    public function testPromiseRejected() {
        $exception = new \Exception;

        $promise = new PromiseMock(new Failure($exception));

        $promise = Promise\adapt($promise);

        $promise->onResolve(function ($exception, $value) use (&$reason) {
            $reason = $exception;
        });

        $this->assertSame($exception, $reason);
    }

    /**
     * @expectedException \Error
     */
    public function testScalarValue() {
        Promise\adapt(1);
    }

    /**
     * @expectedException \Error
     */
    public function testNonThenableObject() {
        Promise\adapt(new \stdClass);
    }
}
