<?php

namespace Amp\Test;

use Amp\CancelledException;
use Amp\Delayed;
use Amp\Loop;
use Amp\PHPUnit\TestCase;
use Amp\TimeoutCancellationToken;
use Amp\TimeoutException;

class TimeoutCancellationTokenTest extends TestCase
{
    public function testTimeout()
    {
        Loop::run(function () {
            $token = new TimeoutCancellationToken(10);

            $this->assertFalse($token->isRequested());
            yield new Delayed(20);
            $this->assertTrue($token->isRequested());

            try {
                $token->throwIfRequested();
            } catch (CancelledException $exception) {
                $this->assertInstanceOf(TimeoutException::class, $exception->getPrevious());
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
