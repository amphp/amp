<?php

namespace Amp\Test;

use Amp\CountdownBarrier;
use Amp\Loop;
use InvalidArgumentException;
use RuntimeException;

class CountdownBarrierTest extends \PHPUnit\Framework\TestCase
{
    /** @var \Amp\CountdownBarrier */
    private $countdownEvent;

    public function setUp()
    {
        $this->countdownEvent = new CountdownBarrier(2);
    }

    public function testSignaledTwoTimes()
    {
        Loop::run(function () {
            $testedValue = null;

            $this->countdownEvent->promise()->onResolve(function ($exception, $value) use (&$testedValue) {
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
        new CountdownBarrier(0);
    }
}
