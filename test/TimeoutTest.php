<?php

namespace Amp\Test;

use Amp\Failure;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Promise;
use Amp\Success;
use Amp\TimeoutException;
use function Amp\asyncValue;
use function Amp\await;
use function Revolt\EventLoop\delay;

class TimeoutTest extends AsyncTestCase
{
    public function testSuccessfulPromise(): void
    {
        $value = 1;

        $promise = new Success($value);

        $promise = Promise\timeout($promise, 10);
        self::assertInstanceOf(Promise::class, $promise);

        $callback = function ($exception, $value) use (&$result) {
            $result = $value;
        };

        $promise->onResolve($callback);

        self::assertSame($value, await($promise));
    }

    public function testFailedPromise(): void
    {
        $exception = new \Exception;

        $promise = new Failure($exception);

        try {
            await(Promise\timeout($promise, 10));
        } catch (\Throwable $reason) {
            self::assertSame($exception, $reason);
            return;
        }

        self::fail("Promise should have failed");
    }

    /**
     * @depends testSuccessfulPromise
     */
    public function testFastPending(): void
    {
        $value = 1;

        $promise = asyncValue(10, $value);

        self::assertSame($value, await(Promise\timeout($promise, 20)));

        delay(0); // Tick event loop to invoke onResolve callback to remove watcher.
    }

    /**
     * @depends testSuccessfulPromise
     */
    public function testSlowPending(): void
    {
        $promise = asyncValue(20);

        try {
            await(Promise\timeout($promise, 10));
            self::fail("Promise did not fail");
        } catch (TimeoutException $reason) {
            self::assertNull(await($promise));
        }
    }

    /**
     * @depends testSuccessfulPromise
     */
    public function testSlowPendingWithDefault(): void
    {
        $value = 0;
        $default = 1;

        $promise = asyncValue(20, $value);

        self::assertSame($default, await(Promise\timeoutWithDefault($promise, 10, $default)));

        self::assertSame($value, await($promise));
    }
}
