<?php

namespace Amp\Test\Loop;

use Amp\Loop\Driver;
use Amp\Loop\NativeDriver;

class NativeDriverTest extends DriverTest
{
    public function getFactory(): callable
    {
        return function () {
            return new NativeDriver;
        };
    }

    public function testHandle()
    {
        $this->assertNull($this->loop->getHandle());
    }

    /**
     * @requires PHP 7.1
     */
    public function testAsyncSignals()
    {
        \pcntl_async_signals(true);

        try {
            $this->start(function (Driver $loop) use (&$invoked) {
                $watcher = $loop->onSignal(SIGUSR1, function () use (&$invoked) {
                    $invoked = true;
                });
                $loop->unreference($watcher);
                $loop->defer(function () {
                    \posix_kill(\getmypid(), \SIGUSR1);
                });
            });
        } finally {
            \pcntl_async_signals(false);
        }

        $this->assertTrue($invoked);
    }
}
