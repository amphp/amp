<?php

namespace Amp;

use PHPUnit\Framework\TestCase;

class TimingTest extends TestCase
{
    public function testTiming()
    {
        $timing = new Timing;
        $timing->start('foo');
        \usleep(5000);
        $timing->end('foo');

        $this->assertGreaterThanOrEqual(5, $timing->getDuration('foo'));
    }

    public function testTimingNotStarted()
    {
        $this->expectException(\Error::class);

        $timing = new Timing;
        $timing->end('foo');
    }

    public function testTimingAlreadyStarted()
    {
        $this->expectException(\Error::class);

        $timing = new Timing;
        $timing->start('foo');
        $timing->start('foo');
    }

    public function testTimingAlreadyEnded()
    {
        $this->expectException(\Error::class);

        $timing = new Timing;
        $timing->start('foo');
        $timing->end('foo');
        $timing->end('foo');
    }

    public function testTimingNonExistent()
    {
        $this->expectException(\Error::class);

        $timing = new Timing;
        $timing->getDuration('foo');
    }
}
