<?php

namespace Amp\Cancellation;

use Amp\CancelledException;
use Amp\SignalCancellation;
use Amp\SignalException;
use Amp\TestCase;
use Revolt\EventLoop;
use function Amp\delay;

/**
 * @requires extension pcntl
 * @requires extension posix
 */
class SignalCancellationTest extends TestCase
{
    public function testSignal(): void
    {
        $line = __LINE__ + 1;
        $cancellation = new SignalCancellation([\SIGUSR1, \SIGUSR2]);

        self::assertFalse($cancellation->isRequested());

        EventLoop::defer(function (): void {
            \posix_kill(\getmypid(), \SIGUSR1);
        });

        delay(0.1);

        self::assertTrue($cancellation->isRequested());

        try {
            $cancellation->throwIfRequested();
        } catch (CancelledException $exception) {
            self::assertInstanceOf(SignalException::class, $exception->getPrevious());

            $message = $exception->getPrevious()->getMessage();

            if ((int) \ini_get('zend.assertions') > 0) {
                self::assertStringContainsString('SignalCancellation was created here', $message);
                self::assertStringContainsString('SignalCancellationTest.php:' . $line, $message);
            }
        }
    }

    public function testWatcherCancellation(): void
    {
        $identifiers = EventLoop::getIdentifiers();
        $cancellation = new SignalCancellation([\SIGUSR1, \SIGUSR2]);
        self::assertSame(\count($identifiers) + 2, \count(EventLoop::getIdentifiers()));
        unset($cancellation);
        self::assertSame($identifiers, EventLoop::getIdentifiers());
    }
}
