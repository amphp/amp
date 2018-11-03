<?php

namespace Amp\Test;

use Amp\CountdownBarrier;
use Amp\Loop;
use InvalidArgumentException;
use RuntimeException;

class CountdownBarrierTest extends \PHPUnit\Framework\TestCase
{
    /** @var \Amp\CountdownBarrier */
    private $countdownBarrier;

    public function setUp()
    {
        $this->countdownBarrier = new CountdownBarrier(2);
    }

    public function testSignalUntilResolved()
    {
        Loop::run(function () {
            $testedValue = null;

            $this->countdownBarrier->promise()->onResolve(function ($exception, $value) use (&$testedValue) {
                $testedValue = $value;
            });

            $this->assertNull($testedValue);

            $this->assertFalse($this->countdownBarrier->signal());

            $this->assertNull($testedValue);

            $this->assertTrue($this->countdownBarrier->signal());

            $this->assertTrue($testedValue);
        });
    }

    public function testSignalAfterResolved()
    {
        Loop::run(function () {
            $this->countdownBarrier->signal();
            $this->countdownBarrier->signal();

            $this->expectException(RuntimeException::class);
            $this->countdownBarrier->signal();
        });
    }

    public function testSignalWithCount()
    {
        Loop::run(function () {
            $testedValue = null;

            $this->countdownBarrier->promise()->onResolve(function ($exception, $value) use (&$testedValue) {
                $testedValue = $value;
            });

            $this->assertNull($testedValue);

            $this->assertTrue($this->countdownBarrier->signal(2));

            $this->assertTrue($testedValue);
        });
    }

    public function testSignalWithInvalidCount()
    {
        $this->expectException(InvalidArgumentException::class);

        $this->countdownBarrier->signal(0);
    }

    public function testInvalidSignalCountInConstructor()
    {
        $this->expectException(InvalidArgumentException::class);
        new CountdownBarrier(0);
    }

    public function testReset()
    {
        Loop::run(function () {
            $testedValue = null;

            $this->countdownBarrier->promise()->onResolve(function ($exception, $value) use (&$testedValue) {
                $testedValue = $value;
            });

            $this->assertFalse($this->countdownBarrier->signal());
            $this->countdownBarrier->reset();
            $this->assertFalse($this->countdownBarrier->signal());
            $this->assertTrue($this->countdownBarrier->signal());

            $this->assertTrue($testedValue);
        });
    }

    public function testResetWithSignalCount()
    {
        Loop::run(function () {
            $testedValue = null;

            $this->countdownBarrier->promise()->onResolve(function ($exception, $value) use (&$testedValue) {
                $testedValue = $value;
            });

            $this->assertFalse($this->countdownBarrier->signal());
            $this->countdownBarrier->reset(3);
            $this->assertFalse($this->countdownBarrier->signal());
            $this->assertFalse($this->countdownBarrier->signal());
            $this->assertTrue($this->countdownBarrier->signal());

            $this->assertTrue($testedValue);
        });
    }

    public function testResetWithInvalidSignalCount()
    {
        $this->expectException(InvalidArgumentException::class);

        $this->countdownBarrier->reset(0);
    }

    public function testResetWithResolvedPromise()
    {
        $this->countdownBarrier->signal();
        $this->assertTrue($this->countdownBarrier->signal());

        $this->countdownBarrier->reset();

        $this->countdownBarrier->signal();
        $this->assertTrue($this->countdownBarrier->signal());
    }

    public function testAddCount()
    {
        Loop::run(function () {
            $testedValue = null;

            $this->countdownBarrier->promise()->onResolve(function ($exception, $value) use (&$testedValue) {
                $testedValue = $value;
            });

            $this->assertFalse($this->countdownBarrier->signal());
            $this->countdownBarrier->addCount();
            $this->assertFalse($this->countdownBarrier->signal());
            $this->assertTrue($this->countdownBarrier->signal());

            $this->assertTrue($testedValue);
        });
    }

    public function testAddCountWithSignalCount()
    {
        Loop::run(function () {
            $testedValue = null;

            $this->countdownBarrier->promise()->onResolve(function ($exception, $value) use (&$testedValue) {
                $testedValue = $value;
            });

            $this->assertFalse($this->countdownBarrier->signal());
            $this->countdownBarrier->addCount(2);
            $this->assertFalse($this->countdownBarrier->signal());
            $this->assertFalse($this->countdownBarrier->signal());
            $this->assertTrue($this->countdownBarrier->signal());

            $this->assertTrue($testedValue);
        });
    }

    public function testAddCountWithInvalidSignalCount()
    {
        $this->expectException(InvalidArgumentException::class);

        $this->countdownBarrier->addCount(0);
    }

    public function testAddCountWithResolvedPromise()
    {
        $this->countdownBarrier->signal();
        $this->countdownBarrier->signal();

        $this->expectException(RuntimeException::class);

        $this->countdownBarrier->addCount(1);
    }
}
