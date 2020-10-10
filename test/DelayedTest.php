<?php

namespace Amp\Test;

use Amp\Delayed;
use Amp\PHPUnit\AsyncTestCase;
use function Amp\await;

class DelayedTest extends AsyncTestCase
{
    public function testDelayed(): void
    {
        $this->setMinimumRuntime(100);

        $time = 100;
        $value = "test";

        $promise = new Delayed($time, $value);

        $this->assertSame($value, await($promise));
    }

    public function testReference(): void
    {
        $this->setMinimumRuntime(100);

        $time = 100;
        $value = "test";

        $promise = new Delayed($time, $value);
        $promise->unreference();
        $promise->reference();

        await($promise);
    }
}
