<?php declare(strict_types=1);

namespace Amp\Cancellation;

use Amp\CancelledException;
use Amp\DeferredFuture;
use Amp\TestCase;
use Amp\TimeoutCancellation;
use Amp\TimeoutException;
use Revolt\EventLoop;
use function Amp\delay;

class TimeoutCancellationTest extends TestCase
{
    public function testTimeout(): void
    {
        $line = __LINE__ + 1;
        $cancellation = new TimeoutCancellation(0.01);

        self::assertFalse($cancellation->isRequested());
        delay(0.02);
        self::assertTrue($cancellation->isRequested());

        try {
            $cancellation->throwIfRequested();
        } catch (CancelledException $exception) {
            self::assertInstanceOf(TimeoutException::class, $exception->getPrevious());

            $message = $exception->getPrevious()->getMessage();

            if ((int) \ini_get('zend.assertions') > 0) {
                self::assertStringContainsString('TimeoutCancellation was created here', $message);
                self::assertStringContainsString('TimeoutCancellationTest.php:' . $line, $message);
            }
        }
    }

    public function testWatcherCancellation(): void
    {
        $identifiers = EventLoop::getIdentifiers();
        $cancellation = new TimeoutCancellation(0.001);
        self::assertSame(\count($identifiers) + 1, \count(EventLoop::getIdentifiers()));
        unset($cancellation);
        self::assertSame($identifiers, EventLoop::getIdentifiers());
    }

    public function testWatcherUnreference(): void
    {
        $this->expectExceptionMessageMatches("/Event loop terminated without resuming the current suspension/");
        $deferred = new DeferredFuture;
        $cancellation = new TimeoutCancellation(0.001);
        $deferred->getFuture()->await($cancellation);
    }

    public function testWatcherNoUnreference(): void
    {
        $this->expectException(CancelledException::class);
        $cancellation = new TimeoutCancellation(0.001, unreference: false);
        $deferred = new DeferredFuture;
        $deferred->getFuture()->await($cancellation);
    }
}
