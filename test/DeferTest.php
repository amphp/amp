<?php

namespace Amp\Test;

use Amp\Loop;
use Amp\PHPUnit\AsyncTestCase;
use Amp\PHPUnit\TestException;
use function Amp\defer;
use function Amp\delay;

class DeferTest extends AsyncTestCase
{
    public function testExceptionsRethrownToLoopHandler(): void
    {
        Loop::setErrorHandler(function (\Throwable $exception) use (&$reason): void {
            $reason = $exception;
        });

        $exception = new TestException;

        defer(function () use ($exception): void {
            throw $exception;
        });

        delay(1); // Tick event loop.

        $this->assertSame($exception, $reason);
    }
}
