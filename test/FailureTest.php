<?php

namespace Amp\Test;

use Amp\Failure;
use Amp\Loop;
use React\Promise\RejectedPromise as RejectedReactPromise;

class FailureTest extends BaseTest
{
    public function testConstructWithNonException(): void
    {
        $this->expectException(\TypeError::class);

        $failure = new Failure(1);
    }

    public function testOnResolve(): void
    {
        $exception = new \Exception;

        $invoked = 0;
        $callback = function ($exception, $value) use (&$invoked, &$reason) {
            ++$invoked;
            $reason = $exception;
        };

        $failure = new Failure($exception);

        $failure->onResolve($callback);

        self::assertSame(1, $invoked);
        self::assertSame($exception, $reason);
    }

    public function testOnResolveWithReactPromise(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Success');

        Loop::run(function () {
            $failure = new Failure(new \Exception);
            $failure->onResolve(function ($exception, $value) {
                return new RejectedReactPromise(new \Exception("Success"));
            });
        });
    }

    public function testOnResolveWithGenerator(): void
    {
        $exception = new \Exception;
        $failure = new Failure($exception);
        $invoked = false;
        $failure->onResolve(function ($exception, $value) use (&$invoked) {
            $invoked = true;
            return $exception;
            yield; // Unreachable, but makes function a generator.
        });

        self::assertTrue($invoked);
    }
}
