<?php

namespace Amp\Test;

use Amp\Loop;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Signal;
use function Amp\await;

/**
 * @requires ext-pcntl
 */
class SignalTest extends AsyncTestCase
{
    public function testDelayed(): void
    {
        $this->setMinimumRuntime(20);

        $promise = new Signal(\SIGUSR1, \SIGUSR2);

        Loop::delay(10, fn() => \posix_kill(\getmypid(), \SIGUSR1));

        $this->assertSame(\SIGUSR1, await($promise));

        $promise = new Signal(\SIGUSR1, \SIGUSR2);

        Loop::delay(10, fn() => \posix_kill(\getmypid(), \SIGUSR2));

        $this->assertSame(\SIGUSR2, await($promise));
    }

    public function testReference(): void
    {
        $this->setMinimumRuntime(10);

        Loop::delay(10, fn() => \posix_kill(\getmypid(), \SIGUSR1));

        $promise = new Signal(\SIGUSR1, \SIGUSR2);
        $promise->unreference();
        $promise->reference();

        await($promise);
    }
}
