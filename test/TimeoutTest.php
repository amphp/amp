<?php

namespace Amp\Test;

use Amp\TimeoutException;
use Concurrent\AsyncTestCase;
use Concurrent\Deferred;
use Concurrent\Task;
use function Amp\delay;
use function Amp\timeout;

class TimeoutTest extends AsyncTestCase
{
    public function testSuccessful(): void
    {
        $value = 1;

        $this->assertSame($value, timeout(Deferred::value($value), 100));
    }

    public function testFailure(): void
    {
        $exception = new \Exception;

        $awaitable = Deferred::error($exception);

        $this->expectExceptionObject($exception);

        timeout($awaitable, 100);
    }

    /**
     * @depends testSuccessful
     */
    public function testFastPending(): void
    {
        $value = 1;

        $awaitable = Task::async(function () use ($value) {
            delay(50);

            return $value;
        });

        $this->assertSame($value, timeout($awaitable, 100));
    }

    /**
     * @depends testSuccessful
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
            timeout($awaitable, 100);
        } finally {
            Task::await($awaitable); // await to clear pending watchers
        }
    }
}
