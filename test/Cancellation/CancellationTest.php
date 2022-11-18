<?php

namespace Amp\Cancellation;

use Amp\DeferredCancellation;
use Amp\TestCase;
use Revolt\EventLoop;
use function Amp\delay;

class CancellationTest extends TestCase
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

        self::assertFalse($deferredCancellation->isCancelled());

        $deferredCancellation->cancel();

        self::assertTrue($deferredCancellation->isCancelled());
    }

    public function testThrowingCallbacksEndUpInLoop(): void
    {
        EventLoop::setErrorHandler(function (\Throwable $exception) use (&$reason): void {
            $reason = $exception;
        });

        $cancellationSource = new DeferredCancellation;
        $cancellationSource->getCancellation()->subscribe(function () {
            throw new \Exception('testThrowingCallbacksEndUpInLoop message');
        });

        $cancellationSource->cancel();

        delay(0.01); // Tick event loop to invoke callbacks.

        self::assertInstanceOf(\Exception::class, $reason);
        self::assertSame('testThrowingCallbacksEndUpInLoop message', $reason->getMessage());
    }

    public function testDoubleCancelOnlyInvokesOnce(): void
    {
        $cancellationSource = new DeferredCancellation;
        $cancellationSource->getCancellation()->subscribe($this->createCallback(1));

        $cancellationSource->cancel();
        $cancellationSource->cancel();

        delay(0); // tick event loop
    }

    public function testCalledIfSubscribingAfterCancel(): void
    {
        $cancellationSource = new DeferredCancellation;
        $cancellationSource->cancel();
        $cancellationSource->getCancellation()->subscribe($this->createCallback(1));

        delay(0); // tick event loop
    }

    public function testCancelOnDestruct(): void
    {
        $cancellationSource = new DeferredCancellation;
        $cancellationSource->getCancellation()->subscribe($this->createCallback(1));
        unset($cancellationSource);

        delay(0); // tick event loop
    }
}
