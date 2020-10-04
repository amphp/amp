<?php

namespace Amp\Test;

use Amp\Delayed;
use Amp\PHPUnit\AsyncTestCase;
use function Amp\await;

class DelayedTest extends AsyncTestCase
{
    public function testDelayed(): void
    {
        $time = 100;
        $value = "test";
        $start = \microtime(true);

        $promise = new Delayed($time, $value);

        $this->assertSame($value, await($promise));

        $this->assertGreaterThanOrEqual($time - 1 /* 1ms grace period */, (\microtime(true) - $start) * 1000);
    }

    public function testReference(): void
    {
        $time = 100;
        $value = "test";
        $start = \microtime(true);

        $promise = new Delayed($time, $value);
        $promise->unreference();
        $promise->reference();

        await($promise);

        $this->assertGreaterThanOrEqual($time - 1 /* 1ms grace period */, (\microtime(true) - $start) * 1000);
    }
}
