<?php

namespace Amp\Test;

use Amp\CancelledException;
use Amp\PHPUnit\AsyncTestCase;
use Amp\TimeoutCancellationToken;
use Amp\TimeoutException;
use Revolt\EventLoop\Loop;
use function Revolt\EventLoop\delay;

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
            self::assertStringContainsString('TimeoutCancellationToken was created here', $message);
            self::assertStringContainsString('TimeoutCancellationTokenTest.php:' . $line, $message);
        }
    }

    public function testWatcherCancellation(): void
    {
        $token = new TimeoutCancellationToken(0.001);
        self::assertSame(1, Loop::getInfo()["delay"]["enabled"]);
        unset($token);
        self::assertSame(0, Loop::getInfo()["delay"]["enabled"]);
    }
}
