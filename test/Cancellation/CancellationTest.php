<?php

namespace Amp\Cancellation;

use Amp\DeferredCancellation;
use Amp\PHPUnit\AsyncTestCase;
use Amp\PHPUnit\TestException;
use Revolt\EventLoop;
use function Amp\delay;

class CancellationTest extends AsyncTestCase
{
    public function testUnsubscribeWorks(): void
    {
        $deferredCancellation = new DeferredCancellation;

        $id = $deferredCancellation->getCancellation()->subscribe(function () {
            $this->fail("Callback has been called");
        });

        $deferredCancellation->getCancellation()->subscribe(function () {
            $this->assertTrue(true);
        });

        $deferredCancellation->getCancellation()->unsubscribe($id);

        $deferredCancellation->cancel();
    }

    public function testThrowingCallbacksEndUpInLoop(): void
    {
        EventLoop::setErrorHandler(function (\Throwable $exception) use (&$reason): void {
            $reason = $exception;
        });

        $cancellationSource = new DeferredCancellation;
        $cancellationSource->getCancellation()->subscribe(function () {
            throw new TestException;
        });

        $cancellationSource->cancel();

        delay(0.01); // Tick event loop to invoke callbacks.

        self::assertInstanceOf(TestException::class, $reason);
    }

    public function testDoubleCancelOnlyInvokesOnce(): void
    {
        $cancellationSource = new DeferredCancellation;
        $cancellationSource->getCancellation()->subscribe(\Closure::fromCallable($this->createCallback(1)));

        $cancellationSource->cancel();
        $cancellationSource->cancel();
    }

    public function testCalledIfSubscribingAfterCancel(): void
    {
        $cancellationSource = new DeferredCancellation;
        $cancellationSource->cancel();
        $cancellationSource->getCancellation()->subscribe(\Closure::fromCallable($this->createCallback(1)));
    }
}
