<?php

namespace Amp\Test;

use Amp\CancelledException;
use Amp\Loop;
use Amp\PHPUnit\AsyncTestCase;
use Amp\TimeoutCancellationToken;
use Amp\TimeoutException;
use function Amp\delay;

class TimeoutCancellationTokenTest extends AsyncTestCase
{
    public function testTimeout(): void
    {
        $line = __LINE__ + 1;
        $token = new TimeoutCancellationToken(10);

        $this->assertFalse($token->isRequested());
        delay(20);
        $this->assertTrue($token->isRequested());

        try {
            $token->throwIfRequested();
        } catch (CancelledException $exception) {
            $this->assertInstanceOf(TimeoutException::class, $exception->getPrevious());

            $message = $exception->getPrevious()->getMessage();
            $this->assertStringContainsString('TimeoutCancellationToken was created here', $message);
            $this->assertStringContainsString('TimeoutCancellationTokenTest.php:' . $line, $message);
        }
    }

    public function testWatcherCancellation(): void
    {
        $token = new TimeoutCancellationToken(1);
        $this->assertSame(1, Loop::getInfo()["delay"]["enabled"]);
        unset($token);
        $this->assertSame(0, Loop::getInfo()["delay"]["enabled"]);
    }
}
