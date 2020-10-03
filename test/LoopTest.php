<?php

namespace Amp\Test;

use Amp\Deferred;
use Amp\Loop;
use Amp\PHPUnit\AsyncTestCase;
use function Amp\asyncCallable;
use function Amp\await;
use function Amp\sleep;

class LoopTest extends AsyncTestCase
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
        $ends = \stream_socket_pair(
            \stripos(PHP_OS, "win") === 0 ? STREAM_PF_INET : STREAM_PF_UNIX,
            STREAM_SOCK_STREAM,
            STREAM_IPPROTO_IP
        );
        \fwrite($ends[0], "trigger readability watcher");

        $deferred = new Deferred;

        Loop::onReadable($ends[1], function ($watcher) use ($deferred): void {
            $this->assertTrue(true);
            Loop::cancel($watcher);
            $deferred->resolve();
        });

        await($deferred->promise());
    }

    public function testOnWritable()
    {
        $deferred = new Deferred;

        Loop::onWritable(STDOUT, function ($watcher) use ($deferred): void {
            $this->assertTrue(true);
            Loop::cancel($watcher);
            $deferred->resolve();
        });

        await($deferred->promise());
    }

    public function testNow(): void
    {
        $deferred = new Deferred;

        $now = Loop::now();
        Loop::delay(100, function () use ($now, $deferred): void {
            $now += 100;
            $new = Loop::now();

            // Allow a few milliseconds of inaccuracy.
            $this->assertGreaterThanOrEqual($now - 1, $new);
            $this->assertLessThanOrEqual($now + 100, $new);

            $deferred->resolve();
        });

        await($deferred->promise());
    }

    public function testGet(): void
    {
        $this->assertInstanceOf(Loop\Driver::class, Loop::get());
    }

    public function testGetInfo(): void
    {
        $this->assertSame(Loop::get()->getInfo(), Loop::getInfo());
    }

    public function testBug163ConsecutiveDelayed(): void
    {
        $deferred = new Deferred;

        $emits = 3;

        Loop::defer(asyncCallable(function () use (&$time, $deferred, $emits) {
            try {
                $time = \microtime(true);
                for ($i = 0; $i < $emits; ++$i) {
                    sleep(100);
                }
                $time = \microtime(true) - $time;
                $deferred->resolve();
            } catch (\Throwable $exception) {
                $deferred->fail($exception);
            }
        }));

        await($deferred->promise());

        $this->assertGreaterThan(100 * $emits - 1 /* 1ms grace period */, $time * 1000);
    }
}
