<?php

namespace Amp\Test;

use Amp\CancelledException;
use Amp\Loop;
use Amp\TimeoutCancellationToken;
use Amp\TimeoutException;
use function Amp\delay;

class TimeoutCancellationTokenTest extends BaseTest
{
    public function testTimeout()
    {
        Loop::run(function () {
            $line = __LINE__ + 1;
            $token = new TimeoutCancellationToken(10);

            $this->assertFalse($token->isRequested());
            yield delay(20);
            $this->assertTrue($token->isRequested());

            try {
                $token->throwIfRequested();
            } catch (CancelledException $exception) {
                $this->assertInstanceOf(TimeoutException::class, $exception->getPrevious());

                $message = $exception->getPrevious()->getMessage();
                $this->assertContains('TimeoutCancellationToken was created here', $message);
                $this->assertContains('TimeoutCancellationTokenTest.php:' . $line, $message);
            }
        });
    }

    public function testWatcherCancellation()
    {
        Loop::run(function () {
            $token = new TimeoutCancellationToken(1);
            $this->assertSame(1, Loop::getInfo()["delay"]["enabled"]);
            unset($token);
            $this->assertSame(0, Loop::getInfo()["delay"]["enabled"]);
        });
    }
}
