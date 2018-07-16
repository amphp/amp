<?php

namespace Amp\Test;

use Amp\TimeoutException;
use Concurrent\Deferred;
use Concurrent\Task;
use PHPUnit\Framework\TestCase;
use function Amp\delay;
use function Amp\timeout;

class TimeoutTest extends TestCase
{
    public function testSuccessfulPromise(): void
    {
        $value = 1;

        $awaitable = Deferred::value($value);
        $awaitable = timeout($awaitable, 100);

        $this->assertSame($value, Task::await($awaitable));
    }

    public function testFailedPromise(): void
    {
        $exception = new \Exception;

        $awaitable = Deferred::error($exception);
        $awaitable = timeout($awaitable, 100);

        $this->expectExceptionObject($exception);
        Task::await($awaitable);
    }

    /**
     * @depends testSuccessfulPromise
     */
    public function testFastPending(): void
    {
        $value = 1;

        $awaitable = Task::async(function () use ($value) {
            delay(50);

            return $value;
        });

        $this->assertSame($value, Task::await(timeout($awaitable, 100)));
    }

    /**
     * @depends testSuccessfulPromise
     */
    public function testSlowPending(): void
    {
        $value = 1;

        $awaitable = Task::async(function () use ($value) {
            delay(200);

            return $value;
        });

        $this->expectException(TimeoutException::class);

        try {
            Task::await(timeout($awaitable, 100));
        } finally {
            Task::await($awaitable); // await to clear pending watchers
        }
    }
}
