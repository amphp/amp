<?php

namespace Amp\Test;

use Amp\CountdownEvent;
use Amp\Loop;
use InvalidArgumentException;
use RuntimeException;

class CountdownEventTest extends \PHPUnit\Framework\TestCase
{
    /** @var \Amp\CountdownEvent */
    private $countdownEvent;

    public function setUp()
    {
        $this->countdownEvent = new CountdownEvent(2);
    }

    public function testSignaledTwoTimes()
    {
        Loop::run(function () {
            $testedValue = null;

            $this->countdownEvent->onResolve(function ($exception, $value) use (&$testedValue) {
                $testedValue = $value;
            });

            $this->assertNull($testedValue);

            $this->countdownEvent->signal();

            $this->assertNull($testedValue);

            $this->countdownEvent->signal();

            $this->assertTrue($testedValue);
        });
    }

    public function testSignaledThreeTimes()
    {
        Loop::run(function () {
            $this->countdownEvent->signal();
            $this->countdownEvent->signal();

            $this->expectException(RuntimeException::class);
            $this->countdownEvent->signal();
        });
    }

    public function testInvalidCounter()
    {
        $this->expectException(InvalidArgumentException::class);
        new CountdownEvent(0);
    }
}
