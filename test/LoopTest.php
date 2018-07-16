<?php

namespace Amp\Test;

use Amp\Loop;
use PHPUnit\Framework\TestCase;

class LoopTest extends TestCase
{
    public function testDelayWithNegativeDelay(): void
    {
        $this->expectException(\Error::class);

        Loop::delay(-1, function () {
        });
    }

    public function testRepeatWithNegativeInterval(): void
    {
        $this->expectException(\Error::class);

        Loop::repeat(-1, function () {
        });
    }

    public function testOnReadable(): void
    {
        Loop::run(function () {
            $ends = \stream_socket_pair(\stripos(PHP_OS, "win") === 0 ? STREAM_PF_INET : STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
            \fwrite($ends[0], "trigger readability watcher");

            Loop::onReadable($ends[1], function ($watcherId) {
                $this->assertTrue(true);
                Loop::cancel($watcherId);
                Loop::stop();
            });
        });
    }

    public function testOnWritable(): void
    {
        Loop::run(function () {
            Loop::onWritable(STDOUT, function ($watcherId) {
                $this->assertTrue(true);
                Loop::cancel($watcherId);
                Loop::stop();
            });
        });
    }

    public function testGet(): void
    {
        $this->assertInstanceOf(Loop\Driver::class, Loop::get());
    }

    public function testGetInto(): void
    {
        $this->assertSame(Loop::get()->getInfo(), Loop::getInfo());
    }
}
