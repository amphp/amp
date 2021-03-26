<?php

namespace Amp\Test;

use Amp\Failure;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Promise;
use Amp\Success;
use function Amp\await;

class PromiseMock
{
    private Promise $promise;

    public function __construct(Promise $promise)
    {
        $this->promise = $promise;
    }

    public function then(callable $onFulfilled = null, callable $onRejected = null)
    {
        $this->promise->onResolve(function ($exception, $value) use ($onFulfilled, $onRejected): void {
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

class AdaptTest extends AsyncTestCase
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

        self::assertSame($value, await($promise));
    }

    /**
     * @depends testThenCalled
     */
    public function testPromiseRejected(): void
    {
        $exception = new \Exception;

        $promise = new PromiseMock(new Failure($exception));

        $promise = Promise\adapt($promise);

        try {
            await($promise);
        } catch (\Exception $reason) {
            self::assertSame($exception, $reason);
            return;
        }

        self::fail("Promise was not failed");
    }
}
