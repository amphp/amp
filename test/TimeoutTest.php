<?php

namespace Amp\Test;

use Amp\Delayed;
use Amp\Failure;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Promise;
use Amp\Success;
use Amp\TimeoutException;
use function Amp\await;
use function Amp\delay;

class TimeoutTest extends AsyncTestCase
{
    public function testSuccessfulPromise(): void
    {
        $value = 1;

        $promise = new Success($value);

        $promise = Promise\timeout($promise, 10);
        $this->assertInstanceOf(Promise::class, $promise);

        $callback = function ($exception, $value) use (&$result) {
            $result = $value;
        };

        $promise->onResolve($callback);

        $this->assertSame($value, await($promise));
    }

    public function testFailedPromise(): void
    {
        $exception = new \Exception;

        $promise = new Failure($exception);

        try {
            await(Promise\timeout($promise, 10));
        } catch (\Throwable $reason) {
            $this->assertSame($exception, $reason);
            return;
        }

        $this->fail("Promise should have failed");
    }

    /**
     * @depends testSuccessfulPromise
     */
    public function testFastPending(): void
    {
        $value = 1;

        $promise = new Delayed(10, $value);

        $this->assertSame($value, await(Promise\timeout($promise, 20)));

        delay(0); // Tick event loop to invoke onResolve callback to remove watcher.
    }

    /**
     * @depends testSuccessfulPromise
     */
    public function testSlowPending(): void
    {
        $promise = new Delayed(20);

        try {
            await(Promise\timeout($promise, 10));
            $this->fail("Promise did not fail");
        } catch (TimeoutException $reason) {
            $this->assertNull(await($promise));
        }
    }

    /**
     * @depends testSuccessfulPromise
     */
    public function testSlowPendingWithDefault(): void
    {
        $value = 0;
        $default = 1;

        $promise = new Delayed(20, $value);

        $this->assertSame($default, await(Promise\timeoutWithDefault($promise, 10, $default)));

        $this->assertSame($value, await($promise));
    }
}
