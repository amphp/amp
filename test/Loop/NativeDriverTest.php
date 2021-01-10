<?php

namespace Amp\Test\Loop;

use Amp\Loop\Driver;
use Amp\Loop\NativeDriver;

class NativeDriverTest extends DriverTest
{
    public function getFactory(): callable
    {
        return function () {
            return new NativeDriver;
        };
    }

    public function testHandle()
    {
        $this->assertNull($this->loop->getHandle());
    }

    public function testTooLargeFileDescriptorSet()
    {
        $sockets = [];
        $domain = \stripos(PHP_OS, 'win') === 0 ? STREAM_PF_INET : STREAM_PF_UNIX;

        for ($i = 0; $i < 1001; $i++) {
            $sockets[] = \stream_socket_pair($domain, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        }

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("You have reached the limits of stream_select(). It has a FD_SETSIZE of 1024, but you have file descriptors numbered at least as high as 200");

        $this->start(function (Driver $loop) use ($sockets) {
            $loop->delay(100, function () {
                // here to provide timeout to stream_select, as the warning is only issued after the system call returns
            });

            foreach ($sockets as list($left, $right)) {
                $loop->onReadable($left, function () {
                    // nothing
                });

                $loop->onReadable($right, function () {
                    // nothing
                });
            }
        });
    }

    public function testSignalDuringStreamSelectIgnored()
    {
        $domain = \stripos(PHP_OS, 'win') === 0 ? STREAM_PF_INET : STREAM_PF_UNIX;
        $sockets = \stream_socket_pair($domain, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);

        $this->start(function (Driver $loop) use ($sockets) {
            $socketWatchers = [
                $loop->onReadable($sockets[0], function () {
                    // nothing
                }),
                $loop->onReadable($sockets[1], function () {
                    // nothing
                }),
            ];

            $loop->onSignal(\SIGUSR2, function ($signalWatcher) use ($socketWatchers, $loop) {
                $loop->cancel($signalWatcher);

                foreach ($socketWatchers as $watcher) {
                    $loop->cancel($watcher);
                }

                $this->assertTrue(true);
            });

            $loop->delay(100, function () {
                \proc_open('sh -c "sleep 1; kill -USR2 ' . \getmypid() . '"', [], $pipes);
            });
        });
    }

    /**
     * @requires PHP 7.1
     */
    public function testAsyncSignals()
    {
        \pcntl_async_signals(true);

        try {
            $this->start(function (Driver $loop) use (&$invoked) {
                $watcher = $loop->onSignal(\SIGUSR1, function () use (&$invoked) {
                    $invoked = true;
                });
                $loop->unreference($watcher);
                $loop->defer(function () {
                    \posix_kill(\getmypid(), \SIGUSR1);
                });
            });
        } finally {
            \pcntl_async_signals(false);
        }

        $this->assertTrue($invoked);
    }
}
