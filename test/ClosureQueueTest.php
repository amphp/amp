<?php

namespace Amp;

use Amp\PHPUnit\AsyncTestCase;

class ClosureQueueTest extends AsyncTestCase
{
    public function testRegister(): void
    {
        $registry = new ClosureQueue();

        $invoked = false;
        $registry->push(function () use (&$invoked): void {
            $invoked = true;
        });

        delay(0); // Give control to event-loop to invoke any queued callbacks (none here)

        self::assertFalse($invoked);

        $registry->call();

        delay(0); // Give control to event-loop to invoke any queued callbacks

        self::assertTrue($invoked);

        $registry->push($this->createCallback(1));

        delay(0); // Give control to event-loop to invoke any queued callbacks (none here)
    }

    public function testCallOnDestruct(): void
    {
        $registry = new ClosureQueue();

        $registry->push($this->createCallback(1));

        unset($registry);

        delay(0); // Give control to event-loop to invoke any queued callbacks (none here)
    }
}
