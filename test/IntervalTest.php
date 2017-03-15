<?php

namespace Amp\Test;

use Amp\Pause;
use Amp\Promise;
use Amp\Stream;
use Amp\Loop;

class IntervalTest extends \PHPUnit\Framework\TestCase {
    const TIMEOUT = 10;

    public function testInterval() {
        $count = 3;
        $stream = Stream\interval(self::TIMEOUT, $count);

        $i = 0;
        $stream = Stream\map($stream, function ($value) use (&$i) {
            $this->assertSame(++$i, $value);
        });

        Promise\wait($stream);

        $this->assertSame($count, $i);
    }

    /**
     * @depends testInterval
     */
    public function testSlowConsumer() {
        $invoked = 0;
        $count = 5;
        Loop::run(function () use (&$invoked, $count) {
            $stream = Stream\interval(self::TIMEOUT, $count);

            $stream->listen(function () use (&$invoked) {
                ++$invoked;
                return new Pause(self::TIMEOUT * 2);
            });
        });

        $this->assertSame($count, $invoked);
    }

    /**
     * @expectedException \Error
     * @expectedExceptionMessage The number of times to emit must be a positive value
     */
    public function testInvalidCount() {
        Stream\interval(self::TIMEOUT, -1);
    }
}
