<?php

namespace Amp\CancellationToken;

use Amp\CancelledException;
use Amp\PHPUnit\AsyncTestCase;
use Amp\TimeoutCancellationToken;
use Amp\TimeoutException;
use Revolt\EventLoop\Loop;
use function Amp\delay;

class TimeoutCancellationTokenTest extends AsyncTestCase
{
    public function testTimeout(): void
    {
        $line = __LINE__ + 1;
        $token = new TimeoutCancellationToken(0.01);

        self::assertFalse($token->isRequested());
        delay(0.02);
        self::assertTrue($token->isRequested());

        try {
            $token->throwIfRequested();
        } catch (CancelledException $exception) {
            self::assertInstanceOf(TimeoutException::class, $exception->getPrevious());

            $message = $exception->getPrevious()->getMessage();

            if ((int)ini_get('zend.assertions') > 0) {
                self::assertStringContainsString('TimeoutCancellationToken was created here', $message);
                self::assertStringContainsString('TimeoutCancellationTokenTest.php:' . $line, $message);
            }
        }
    }

    public function testWatcherCancellation(): void
    {
        $enabled = Loop::getInfo()["delay"]["enabled"];
        $token = new TimeoutCancellationToken(0.001);
        self::assertSame($enabled + 1, Loop::getInfo()["delay"]["enabled"]);
        unset($token);
        self::assertSame($enabled, Loop::getInfo()["delay"]["enabled"]);
    }
}
