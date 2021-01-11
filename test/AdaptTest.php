<?php

namespace Amp\Test;

use Amp\Failure;
use Amp\Promise;
use Amp\Success;

class PromiseMock
{
    /** @var Promise */
    private $promise;

    public function __construct(Promise $promise)
    {
        $this->promise = $promise;
    }

    public function then(callable $onFulfilled = null, callable $onRejected = null): void
    {
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

class AdaptTest extends BaseTest
{
    public function testThenCalled(): void
    {
        $mock = $this->getMockBuilder(PromiseMock::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mock->expects(self::once())
            ->method("then")
            ->with(
                self::callback(function ($resolve) {
                    return \is_callable($resolve);
                }),
                self::callback(function ($reject) {
                    return \is_callable($reject);
                })
            );

        $promise = Promise\adapt($mock);

        self::assertInstanceOf(Promise::class, $promise);
    }

    /**
     * @depends testThenCalled
     */
    public function testPromiseFulfilled(): void
    {
        $value = 1;

        $promise = new PromiseMock(new Success($value));

        $promise = Promise\adapt($promise);

        $promise->onResolve(function ($exception, $value) use (&$result) {
            $result = $value;
        });

        self::assertSame($value, $result);
    }

    /**
     * @depends testThenCalled
     */
    public function testPromiseRejected(): void
    {
        $exception = new \Exception;

        $promise = new PromiseMock(new Failure($exception));

        $promise = Promise\adapt($promise);

        $promise->onResolve(function ($exception, $value) use (&$reason) {
            $reason = $exception;
        });

        self::assertSame($exception, $reason);
    }

    public function testScalarValue(): void
    {
        $this->expectException(\Error::class);

        Promise\adapt(1);
    }

    public function testNonThenableObject(): void
    {
        $this->expectException(\Error::class);

        Promise\adapt(new \stdClass);
    }
}
