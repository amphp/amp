<?php

namespace Amp\Test;

use Amp\Loop;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Promise;
use Amp\Success;
use function Amp\await;
use function Amp\sleep;
use function React\Promise\reject;

class SuccessTest extends AsyncTestCase
{
    public function testConstructWithPromise(): void
    {
        $this->expectException(\Error::class);

        $success = new Success($this->getMockBuilder(Promise::class)->getMock());
    }

    public function testOnResolve(): void
    {
        $value = "Resolution value";

        $success = new Success($value);

        $this->assertSame($value, await($success));
    }

    /**
     * @depends testOnResolve
     */
    public function testOnResolveThrowingForwardsToLoopHandlerOnSuccess(): void
    {
        $invoked = 0;
        $expected = new \Exception;

        Loop::setErrorHandler(function ($exception) use (&$invoked, $expected) {
            ++$invoked;
            $this->assertSame($expected, $exception);
        });

        $callback = function () use ($expected) {
            throw $expected;
        };

        $success = new Success;

        $success->onResolve($callback);

        sleep(0); // Tick event loop to execute onResolve callback.

        $this->assertSame(1, $invoked);
    }

    public function testOnResolveWithReactPromise(): void
    {
        $invoked = 0;
        $success = new Success;

        Loop::setErrorHandler(function (\Throwable $exception) use (&$invoked, &$reason): void {
            ++$invoked;
            $reason = $exception;
        });

        $success->onResolve(fn () => reject(new \Exception("Success")));

        sleep(0); // Tick event loop to execute onResolve callback.

        $this->assertSame(1, $invoked);
    }

    public function testOnResolveWithGenerator(): void
    {
        $value = 1;
        $success = new Success($value);
        $invoked = false;
        $success->onResolve(function ($exception, $value) use (&$invoked) {
            $invoked = true;
            return $value;
            yield; // Unreachable, but makes function a generator.
        });

        $this->assertSame($value, await($success));
        sleep(0); // Tick event loop to execute coroutine
        $this->assertTrue($invoked);
    }
}
