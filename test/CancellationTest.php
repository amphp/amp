<?php

namespace Amp\Test;

use Amp\AsyncGenerator;
use Amp\CancellationToken;
use Amp\CancellationTokenSource;
use Amp\PHPUnit\AsyncTestCase;
use Amp\PHPUnit\TestException;
use Amp\Pipeline;
use Revolt\EventLoop\Loop;
use function Revolt\EventLoop\delay;

class CancellationTest extends AsyncTestCase
{
    public function testCancellationCancelsIterator(): void
    {
        $cancellationSource = new CancellationTokenSource;

        $pipeline = $this->createAsyncIterator($cancellationSource->getToken());

        $count = 0;

        while (null !== $current = $pipeline->continue()) {
            $count++;

            self::assertIsInt($current);

            if ($current === 3) {
                $cancellationSource->cancel();
            }
        }

        self::assertSame(4, $count);
    }

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
        Loop::setErrorHandler(function (\Throwable $exception) use (&$reason): void {
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

    private function createAsyncIterator(CancellationToken $cancellationToken): Pipeline
    {
        return new AsyncGenerator(function () use ($cancellationToken): \Generator {
            $running = true;
            $cancellationToken->subscribe(function () use (&$running): void {
                $running = false;
            });

            $i = 0;
            while ($running) {
                yield $i++;
            }
        });
    }
}
