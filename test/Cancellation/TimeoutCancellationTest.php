<?php

namespace Amp\Cancellation;

use Amp\CancelledException;
use Amp\PHPUnit\AsyncTestCase;
use Amp\TimeoutCancellation;
use Amp\TimeoutException;
use Revolt\EventLoop;
use function Amp\delay;

class TimeoutCancellationTest extends AsyncTestCase
{
    public function testTimeout(): void
    {
        $line = __LINE__ + 1;
        $token = new TimeoutCancellation(0.01);

        self::assertFalse($token->isRequested());
        delay(0.02);
        self::assertTrue($token->isRequested());

        try {
            $token->throwIfRequested();
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
        $enabled = EventLoop::getInfo()["delay"]["enabled"];
        $token = new TimeoutCancellation(0.001);
        self::assertSame($enabled + 1, EventLoop::getInfo()["delay"]["enabled"]);
        unset($token);
        self::assertSame($enabled, EventLoop::getInfo()["delay"]["enabled"]);
    }
}
