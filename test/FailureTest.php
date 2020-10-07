<?php

namespace Amp\Test;

use Amp\Failure;
use Amp\Loop;
use Amp\PHPUnit\AsyncTestCase;
use Amp\PHPUnit\TestException;
use Amp\Promise;
use function Amp\await;
use function Amp\delay;
use function React\Promise\reject;

class FailureTest extends AsyncTestCase
{
    public function testOnResolve(): void
    {
        $exception = new \Exception;

        $failure = new Failure($exception);

        try {
            await($failure);
        } catch (\Exception $reason) {
            $this->assertSame($exception, $reason);
            return;
        }

        $this->fail("Promise was not failed");
    }

    public function testOnResolveWithReactPromise(): void
    {
        $failure = new Failure(new \Exception);
        $failure->onResolve(function () {
            return reject(new \Exception("Success"));
        });

        Loop::setErrorHandler(function (\Throwable $exception) use (&$reason): void {
            $reason = $exception;
        });

        delay(0); // Tick event loop to invoke error callback.

        $this->assertSame("Success", $reason->getMessage());
    }

    public function testOnResolveWithGenerator(): void
    {
        $exception = new \Exception;
        $failure = new Failure($exception);
        $invoked = false;
        $failure->onResolve(function () use (&$invoked): \Generator {
            if (false) {
                yield;
            }

            $invoked = true;
        });

        try {
            await($failure);
        } catch (\Exception $reason) {
            $this->assertSame($exception, $reason);
            delay(0); // Tick event loop to execute coroutine
            $this->assertTrue($invoked);
            return;
        }

        $this->fail("Promise was not failed");
    }
}
