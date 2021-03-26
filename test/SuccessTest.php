<?php

namespace Amp\Test;

use Amp\PHPUnit\AsyncTestCase;
use Amp\Promise;
use Amp\Success;
use Revolt\EventLoop\Loop;
use function Amp\await;
use function Revolt\EventLoop\delay;

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

        self::assertSame($value, await($success));
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

        delay(0); // Tick event loop to execute onResolve callback.

        self::assertSame(1, $invoked);
    }
}
