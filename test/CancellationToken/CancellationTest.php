<?php

namespace Amp\CancellationToken;

use Amp\CancellationTokenSource;
use Amp\PHPUnit\AsyncTestCase;
use Amp\PHPUnit\TestException;
use Revolt\EventLoop;
use function Amp\delay;

class CancellationTest extends AsyncTestCase
{
    public function testUnsubscribeWorks(): void
    {
        $cancellationSource = new CancellationTokenSource;

        $id = $cancellationSource->getToken()->subscribe(function () {
            $this->fail("Callback has been called");
        });

        $cancellationSource->getToken()->subscribe(function () {
            $this->assertTrue(true);
        });

        $cancellationSource->getToken()->unsubscribe($id);

        $cancellationSource->cancel();
    }

    public function testThrowingCallbacksEndUpInLoop(): void
    {
        EventLoop::setErrorHandler(function (\Throwable $exception) use (&$reason): void {
            $reason = $exception;
        });

        $cancellationSource = new CancellationTokenSource;
        $cancellationSource->getToken()->subscribe(function () {
            throw new TestException;
        });

        $cancellationSource->cancel();

        delay(0.01); // Tick event loop to invoke callbacks.

        self::assertInstanceOf(TestException::class, $reason);
    }

    public function testDoubleCancelOnlyInvokesOnce(): void
    {
        $cancellationSource = new CancellationTokenSource;
        $cancellationSource->getToken()->subscribe($this->createCallback(1));

        $cancellationSource->cancel();
        $cancellationSource->cancel();
    }

    public function testCalledIfSubscribingAfterCancel(): void
    {
        $cancellationSource = new CancellationTokenSource;
        $cancellationSource->cancel();
        $cancellationSource->getToken()->subscribe($this->createCallback(1));
    }
}
