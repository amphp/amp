<?php

namespace Amp\Test;

use Amp\Loop;

class LoopTest extends BaseTest
{
    public function testDelayWithNegativeDelay()
    {
        $this->expectException(\Error::class);

        Loop::delay(-1, function () {
        });
    }

    public function testRepeatWithNegativeInterval()
    {
        $this->expectException(\Error::class);

        Loop::repeat(-1, function () {
        });
    }

    public function testOnReadable()
    {
        Loop::run(function () {
            $ends = \stream_socket_pair(
                \stripos(PHP_OS, "win") === 0 ? STREAM_PF_INET : STREAM_PF_UNIX,
                STREAM_SOCK_STREAM,
                STREAM_IPPROTO_IP
            );
            \fwrite($ends[0], "trigger readability watcher");

            Loop::onReadable($ends[1], function ($watcher) {
                $this->assertTrue(true);
                Loop::cancel($watcher);
                Loop::stop();
            });
        });
    }

    public function testOnWritable()
    {
        Loop::run(function () {
            Loop::onWritable(STDOUT, function ($watcher) {
                $this->assertTrue(true);
                Loop::cancel($watcher);
                Loop::stop();
            });
        });
    }

    public function testNow()
    {
        Loop::run(function () {
            $now = Loop::now();
            Loop::delay(100, function () use ($now) {
                $now += 100;
                $new = Loop::now();

                // Allow a few milliseconds of inaccuracy.
                $this->assertGreaterThanOrEqual($now - 1, $new);
                $this->assertLessThanOrEqual($now + 100, $new);
            });
        });
    }

    public function testGet()
    {
        $this->assertInstanceOf(Loop\Driver::class, Loop::get());
    }

    public function testGetInfo()
    {
        $this->assertSame(Loop::get()->getInfo(), Loop::getInfo());
    }
}
