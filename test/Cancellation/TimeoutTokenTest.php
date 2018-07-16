<?php

namespace Amp\Test\Cancellation;

use Amp\Cancellation\CancelledException;
use Amp\Cancellation\TimeoutToken;
use Amp\Loop;
use Amp\TimeoutException;
use PHPUnit\Framework\TestCase;
use function Amp\delay;

class TimeoutTokenTest extends TestCase
{
    public function testTimeout(): void
    {
        $token = new TimeoutToken(10);

        $this->assertFalse($token->isRequested());
        delay(20);
        $this->assertTrue($token->isRequested());

        try {
            $token->throwIfRequested();
            $this->fail("Must throw exception");
        } catch (CancelledException $exception) {
            $this->assertInstanceOf(TimeoutException::class, $exception->getPrevious());
        }
    }

    public function testWatcherCancellation(): void
    {
        $token = new TimeoutToken(1);
        $this->assertSame(1, Loop::getInfo()["delay"]["enabled"]);
        unset($token);
        $this->assertSame(0, Loop::getInfo()["delay"]["enabled"]);
    }
}
