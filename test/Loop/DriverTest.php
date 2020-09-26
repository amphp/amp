<?php

namespace Amp\Test\Loop;

use Amp\Coroutine;
use Amp\Delayed;
use Amp\Failure;
use Amp\Loop;
use Amp\Loop\Driver;
use Amp\Loop\DriverControl;
use Amp\Loop\InvalidWatcherError;
use Amp\Loop\UnsupportedFeatureException;
use PHPUnit\Framework\TestCase;
use React\Promise\RejectedPromise as RejectedReactPromise;
use function Amp\getCurrentTime;

if (!\defined("SIGUSR1")) {
    \define("SIGUSR1", 30);
}
if (!\defined("SIGUSR2")) {
    \define("SIGUSR2", 31);
}

if (!\defined("PHP_INT_MIN")) {
    \define("PHP_INT_MIN", ~PHP_INT_MAX);
}

abstract class DriverTest extends TestCase
{
    /**
     * The DriverFactory to run this test on.
     *
     * @return callable
     */
    abstract public function getFactory(): callable;

    /** @var Driver */
    public $loop;

    public function setUp(): void
    {
        $this->loop = ($this->getFactory())();

        if (!$this->loop instanceof Driver) {
            $this->fail("Factory did not return a loop Driver");
        }

        // Required for error handler to work
        Loop::set($this->loop);
        \gc_collect_cycles();
    }

    public function tearDown(): void
    {
        unset($this->loop);
    }

    protected function start($cb): void
    {
        $control = $this->loop->createControl();
        $cb($this->loop, $control);
        $control->run();
    }

    // Note: The running nesting is important for being able to continue actually still running loops (i.e. running flag set, if the driver has one) inside register_shutdown_function() for example
    public function testLoopRunsCanBeConsecutiveAndNested(): void
    {
        $this->expectOutputString("123456");
        $this->start(function (Driver $loop, DriverControl $control): void {
            $control->stop();
            $loop->defer(function () use (&$run) {
                echo $run = 1;
            });
            $control->run();
            if (!$run) {
                $this->fail("A loop stop before a run must not impact that run");
            }
            $loop->defer(function () use ($loop, $control): void {
                $control->run();
                echo 5;
                $loop->defer(function () use ($loop, $control): void {
                    echo 6;
                    $control->stop();
                    $loop->defer(function () {
                        $this->fail("A loop stopped at all levels must not execute further defers");
                    });
                });
                $control->run();
            });
            $loop->defer(function () use ($loop): void {
                echo 2;
                $loop->defer(function () {
                    echo 4;
                });
            });
            $loop->defer(function (): void {
                echo 3;
            });
        });
    }

    public function testCorrectTimeoutIfBlockingBeforeActivate(): void
    {
        $start = 0;
        $invoked = 0;

        $this->start(function (Driver $loop) use (&$start, &$invoked): void {
            $loop->defer(function () use ($loop, &$start, &$invoked) {
                $start = getCurrentTime();

                $loop->delay(1000, function () use (&$invoked) {
                    $invoked = getCurrentTime();
                });

                \usleep(500000);
            });
        });

        $this->assertNotSame(0, $start);
        $this->assertNotSame(0, $invoked);

        $this->assertGreaterThanOrEqual(999, $invoked - $start);
        $this->assertLessThan(1100, $invoked - $start);
    }

    public function testCorrectTimeoutIfBlockingBeforeDelay(): void
    {
        $start = 0;
        $invoked = 0;

        $this->start(function (Driver $loop) use (&$start, &$invoked): void {
            $start = getCurrentTime();

            \usleep(500000);

            $loop->delay(1000, function () use (&$invoked) {
                $invoked = getCurrentTime();
            });
        });

        $this->assertNotSame(0, $start);
        $this->assertNotSame(0, $invoked);

        $this->assertGreaterThanOrEqual(1500, $invoked - $start);
        $this->assertLessThan(1600, $invoked - $start);
    }

    public function testLoopTerminatesWithOnlyUnreferencedWatchers(): void
    {
        $this->start(function (Driver $loop) use (&$end): void {
            $loop->unreference($loop->onReadable(STDIN, function (): void {
            }));
            $w = $loop->delay(10000000, function (): void {
            });
            $loop->defer(function () use ($loop, $w): void {
                $loop->cancel($w);
            });
            $end = true;
        });
        $this->assertTrue($end);
    }

    /** This MUST NOT have a "test" prefix, otherwise it's executed as test and marked as risky. */
    public function checkForSignalCapability(): void
    {
        try {
            $watcher = $this->loop->onSignal(SIGUSR1, function (): void {
            });
            $this->loop->cancel($watcher);
        } catch (UnsupportedFeatureException $e) {
            $this->markTestSkipped("The loop is not capable of handling signals properly. Skipping.");
        }
    }

    public function testWatcherUnrefRerefRunResult(): void
    {
        $invoked = false;
        $this->start(function (Driver $loop) use (&$invoked): void {
            $watcher = $loop->defer(function () use (&$invoked): void {
                $invoked = true;
            });
            $loop->unreference($watcher);
            $loop->unreference($watcher);
            $loop->reference($watcher);
        });
        $this->assertTrue($invoked);
    }

    public function testDeferWatcherUnrefRunResult(): void
    {
        $invoked = false;
        $this->start(function (Driver $loop) use (&$invoked): void {
            $watcher = $loop->defer(function () use (&$invoked): void {
                $invoked = true;
            });
            $loop->unreference($watcher);
        });
        $this->assertFalse($invoked);
    }

    public function testOnceWatcherUnrefRunResult(): void
    {
        $invoked = false;
        $this->start(function (Driver $loop) use (&$invoked): void {
            $watcher = $loop->delay(2000, function () use (&$invoked): void {
                $invoked = true;
            });
            $loop->unreference($watcher);
            $loop->unreference($watcher);
        });
        $this->assertFalse($invoked);
    }

    public function testRepeatWatcherUnrefRunResult(): void
    {
        $invoked = false;
        $this->start(function (Driver $loop) use (&$invoked): void {
            $watcher = $loop->repeat(2000, function () use (&$invoked): void {
                $invoked = true;
            });
            $loop->unreference($watcher);
        });
        $this->assertFalse($invoked);
    }

    public function testOnReadableWatcherUnrefRunResult(): void
    {
        $invoked = false;
        $this->start(function (Driver $loop) use (&$invoked): void {
            $watcher = $loop->onReadable(STDIN, function () use (&$invoked): void {
                $invoked = true;
            });
            $loop->unreference($watcher);
        });
        $this->assertFalse($invoked);
    }

    public function testOnWritableWatcherKeepAliveRunResult(): void
    {
        $invoked = false;
        $this->start(function (Driver $loop) use (&$invoked): void {
            $watcher = $loop->onWritable(STDOUT, function () use (&$invoked): void {
                $invoked = true;
            });
            $loop->unreference($watcher);
        });
        $this->assertFalse($invoked);
    }

    public function testOnSignalWatcherKeepAliveRunResult(): void
    {
        $this->checkForSignalCapability();
        $invoked = false;
        $this->start(function (Driver $loop) use (&$invoked): void {
            $watcher = $loop->onSignal(SIGUSR1, function () {
                // empty
            });
            $watcher = $loop->delay(100, function () use (&$invoked, $loop, $watcher): void {
                $invoked = true;
                $loop->unreference($watcher);
            });
            $loop->unreference($watcher);
        });
        $this->assertTrue($invoked);
    }

    public function testUnreferencedDeferWatcherStillExecutes(): void
    {
        $invoked = false;
        $this->start(function (Driver $loop) use (&$invoked): void {
            $watcher = $loop->defer(function () use (&$invoked): void {
                $invoked = true;
            });
            $loop->unreference($watcher);
            $loop->defer(function () {
                // just to keep loop running
            });
        });
        $this->assertTrue($invoked);
    }

    public function testLoopDoesNotBlockOnNegativeTimerExpiration(): void
    {
        $invoked = false;
        $this->start(function (Driver $loop) use (&$invoked): void {
            $loop->delay(1, function () use (&$invoked): void {
                $invoked = true;
            });

            \usleep(1000 * 10);
        });
        $this->assertTrue($invoked);
    }

    public function testDisabledDeferReenableInSubsequentTick(): void
    {
        $this->expectOutputString("123");
        $this->start(function (Driver $loop) {
            $watcherId = $loop->defer(function ($watcherId): void {
                echo 3;
            });
            $loop->disable($watcherId);
            $loop->defer(function () use ($loop, $watcherId): void {
                $loop->enable($watcherId);
                echo 2;
            });
            echo 1;
        });
    }

    public function provideRegistrationArgs(): array
    {
        $args = [
            [
                "defer",
                [
                    function () {
                    },
                ],
            ],
            [
                "delay",
                [
                    5,
                    function () {
                    },
                ],
            ],
            [
                "repeat",
                [
                    5,
                    function () {
                    },
                ],
            ],
            [
                "onWritable",
                [
                    \STDOUT,
                    function () {
                    },
                ],
            ],
            [
                "onReadable",
                [
                    \STDIN,
                    function () {
                    },
                ],
            ],
            [
                "onSignal",
                [
                    \SIGUSR1,
                    function () {
                    },
                ],
            ],
        ];

        return $args;
    }

    /**
     * @requires PHP 7
     * @dataProvider provideRegistrationArgs
     */
    public function testWeakTypes(string $type, array $args): void
    {
        if ($type == "onSignal") {
            $this->checkForSignalCapability();
        }

        $this->start(function (Driver $loop) use ($type, $args, &$invoked): void {
            if ($type == "onReadable") {
                $ends = \stream_socket_pair(
                    \stripos(PHP_OS, "win") === 0 ? STREAM_PF_INET : STREAM_PF_UNIX,
                    STREAM_SOCK_STREAM,
                    STREAM_IPPROTO_IP
                );
                \fwrite($ends[0], "trigger readability watcher");
                $args = [$ends[1]];
            } else {
                \array_pop($args);
            }

            $expectedData = 20.75;
            if (\substr($type, 0, 2) == "on") {
                $args[] = function ($watcherId, $arg, int $data) use ($loop, &$invoked, $expectedData): void {
                    $invoked = true;
                    $this->assertSame((int) $expectedData, $data);
                    $loop->unreference($watcherId);
                };
            } else {
                $args[] = function ($watcherId, int $data) use ($loop, &$invoked, $expectedData, $type): void {
                    $invoked = true;
                    $this->assertSame((int) $expectedData, $data);
                    if ($type == "repeat") {
                        $loop->unreference($watcherId);
                    }
                };
            }
            $args[] = $expectedData;
            \call_user_func_array([$loop, $type], $args);

            if ($type == "onSignal") {
                $loop->defer(function (): void {
                    \posix_kill(\getmypid(), \SIGUSR1);
                });
            }
        });

        $this->assertTrue($invoked);
    }

    /** @dataProvider provideRegistrationArgs */
    public function testDisableWithConsecutiveCancel(string $type, array $args): void
    {
        if ($type === "onSignal") {
            $this->checkForSignalCapability();
        }

        $invoked = false;
        $this->start(function (Driver $loop) use (&$invoked, $type, $args): void {
            $func = [$loop, $type];
            $watcherId = \call_user_func_array($func, $args);
            $loop->disable($watcherId);
            $loop->defer(function () use (&$invoked, $loop, $watcherId): void {
                $loop->cancel($watcherId);
                $invoked = true;
            });
            $this->assertFalse($invoked);
        });
        $this->assertTrue($invoked);
    }

    /** @dataProvider provideRegistrationArgs */
    public function testWatcherReferenceInfo(string $type, array $args): void
    {
        if ($type === "onSignal") {
            $this->checkForSignalCapability();
        }

        $loop = $this->loop;

        $func = [$loop, $type];
        if (\substr($type, 0, 2) === "on") {
            $type = "on_" . \lcfirst(\substr($type, 2));
        }

        // being referenced is the default
        $watcherId1 = \call_user_func_array($func, $args);
        $info = $loop->getInfo();
        $expected = ["enabled" => 1, "disabled" => 0];
        $this->assertSame($expected, $info[$type]);
        $expected = ["referenced" => 1, "unreferenced" => 0];
        $this->assertSame($expected, $info["enabled_watchers"]);

        // explicitly reference() even though it's the default setting
        $argsCopy = $args;
        $watcherId2 = \call_user_func_array($func, $argsCopy);
        $loop->reference($watcherId2);
        $loop->reference($watcherId2);
        $info = $loop->getInfo();
        $expected = ["enabled" => 2, "disabled" => 0];
        $this->assertSame($expected, $info[$type]);
        $expected = ["referenced" => 2, "unreferenced" => 0];
        $this->assertSame($expected, $info["enabled_watchers"]);

        // disabling a referenced watcher should decrement the referenced count
        $loop->disable($watcherId2);
        $loop->disable($watcherId2);
        $loop->disable($watcherId2);
        $info = $loop->getInfo();
        $expected = ["referenced" => 1, "unreferenced" => 0];
        $this->assertSame($expected, $info["enabled_watchers"]);

        // enabling a referenced watcher should increment the referenced count
        $loop->enable($watcherId2);
        $loop->enable($watcherId2);
        $info = $loop->getInfo();
        $expected = ["referenced" => 2, "unreferenced" => 0];
        $this->assertSame($expected, $info["enabled_watchers"]);

        // cancelling an referenced watcher should decrement the referenced count
        $loop->cancel($watcherId2);
        $info = $loop->getInfo();
        $expected = ["referenced" => 1, "unreferenced" => 0];
        $this->assertSame($expected, $info["enabled_watchers"]);

        // unreference() should just increment unreferenced count
        $watcherId2 = \call_user_func_array($func, $args);
        $loop->unreference($watcherId2);
        $info = $loop->getInfo();
        $expected = ["enabled" => 2, "disabled" => 0];
        $this->assertSame($expected, $info[$type]);
        $expected = ["referenced" => 1, "unreferenced" => 1];
        $this->assertSame($expected, $info["enabled_watchers"]);

        $loop->cancel($watcherId1);
        $loop->cancel($watcherId2);
    }

    /** @dataProvider provideRegistrationArgs */
    public function testWatcherRegistrationAndCancellationInfo(string $type, array $args): void
    {
        if ($type === "onSignal") {
            $this->checkForSignalCapability();
        }

        $loop = $this->loop;

        $func = [$loop, $type];
        if (\substr($type, 0, 2) === "on") {
            $type = "on_" . \lcfirst(\substr($type, 2));
        }

        $watcherId = \call_user_func_array($func, $args);
        $this->assertIsString($watcherId);
        $info = $loop->getInfo();
        $expected = ["enabled" => 1, "disabled" => 0];
        $this->assertSame($expected, $info[$type]);

        // invoke enable() on active watcher to ensure it has no side-effects
        $loop->enable($watcherId);
        $info = $loop->getInfo();
        $expected = ["enabled" => 1, "disabled" => 0];
        $this->assertSame($expected, $info[$type]);

        // invoke disable() twice to ensure it has no side-effects
        $loop->disable($watcherId);
        $loop->disable($watcherId);

        $info = $loop->getInfo();
        $expected = ["enabled" => 0, "disabled" => 1];
        $this->assertSame($expected, $info[$type]);

        $loop->cancel($watcherId);
        $info = $loop->getInfo();
        $expected = ["enabled" => 0, "disabled" => 0];
        $this->assertSame($expected, $info[$type]);

        $watcherId = \call_user_func_array($func, $args);
        $info = $loop->getInfo();
        $expected = ["enabled" => 1, "disabled" => 0];
        $this->assertSame($expected, $info[$type]);

        $loop->disable($watcherId);
        $info = $loop->getInfo();
        $expected = ["enabled" => 0, "disabled" => 1];
        $this->assertSame($expected, $info[$type]);

        $loop->enable($watcherId);
        $info = $loop->getInfo();
        $expected = ["enabled" => 1, "disabled" => 0];
        $this->assertSame($expected, $info[$type]);

        $loop->cancel($watcherId);
        $info = $loop->getInfo();
        $expected = ["enabled" => 0, "disabled" => 0];
        $this->assertSame($expected, $info[$type]);

        $loop->disable($watcherId);
        $info = $loop->getInfo();
        $expected = ["enabled" => 0, "disabled" => 0];
        $this->assertSame($expected, $info[$type]);
    }

    /**
     * @dataProvider provideRegistrationArgs
     * @group memoryleak
     */
    public function testNoMemoryLeak(string $type, array $args): void
    {
        if ($this->getTestResultObject()->getCollectCodeCoverageInformation()) {
            $this->markTestSkipped("Cannot run this test with code coverage active [code coverage consumes memory which makes it impossible to rely on memory_get_usage()]");
        }

        $runs = 2000;

        if ($type === "onSignal") {
            $this->checkForSignalCapability();
        }

        $this->start(function (Driver $loop, DriverControl $control) use ($type, $args, $runs) {
            $initialMem = \memory_get_usage();
            $cb = function ($runs) use ($loop, $control, $type, $args): void {
                $func = [$loop, $type];
                for ($watchers = [], $i = 0; $i < $runs; $i++) {
                    $watchers[] = \call_user_func_array($func, $args);
                }
                foreach ($watchers as $watcher) {
                    $loop->cancel($watcher);
                }
                for ($watchers = [], $i = 0; $i < $runs; $i++) {
                    $watchers[] = \call_user_func_array($func, $args);
                }
                foreach ($watchers as $watcher) {
                    $loop->disable($watcher);
                    $loop->cancel($watcher);
                }
                for ($watchers = [], $i = 0; $i < $runs; $i++) {
                    $watchers[] = \call_user_func_array($func, $args);
                }
                if ($type === "repeat") {
                    $loop->delay($msInterval = 7, function () use ($loop, $watchers): void {
                        foreach ($watchers as $watcher) {
                            $loop->cancel($watcher);
                        }
                    });
                } elseif ($type !== "defer" && $type !== "delay") {
                    $loop->defer(function () use ($loop, $watchers) {
                        foreach ($watchers as $watcher) {
                            $loop->cancel($watcher);
                        }
                    });
                }
                $control->run();
                if ($type === "defer") {
                    $loop->defer($fn = function ($watcherId, $i) use (&$fn, $loop): void {
                        if ($i) {
                            $loop->defer($fn, --$i);
                        }
                    }, $runs);
                    $control->run();
                }
                if ($type === "delay") {
                    $loop->delay($msDelay = 0, $fn = function ($watcherId, $i) use (&$fn, $loop): void {
                        if ($i) {
                            $loop->delay($msDelay = 0, $fn, --$i);
                        }
                    }, $runs);
                    $control->run();
                }
                if ($type === "repeat") {
                    $loop->repeat($msDelay = 0, $fn = function ($watcherId, $i) use (&$fn, $loop): void {
                        $loop->cancel($watcherId);
                        if ($i) {
                            $loop->repeat($msDelay = 0, $fn, --$i);
                        }
                    }, $runs);
                    $control->run();
                }
                if ($type === "onWritable") {
                    $loop->defer(function ($watcherId, $runs) use ($loop): void {
                        $fn = function ($watcherId, $socket, $i) use (&$fn, $loop): void {
                            $loop->cancel($watcherId);
                            if ($socket) {
                                \fwrite($socket, ".");
                            }
                            if ($i) {
                                // explicitly use *different* streams with *different* resource ids
                                $ends = \stream_socket_pair(
                                    \stripos(
                                        PHP_OS,
                                        "win"
                                    ) === 0 ? STREAM_PF_INET : STREAM_PF_UNIX,
                                    STREAM_SOCK_STREAM,
                                    STREAM_IPPROTO_IP
                                );
                                $loop->onWritable($ends[0], $fn, --$i);
                                $loop->onReadable($ends[1], function ($watcherId) use ($loop): void {
                                    $loop->cancel($watcherId);
                                });
                            }
                        };
                        $fn($watcherId, null, $runs);
                    }, $runs + 1);
                    $control->run();
                }
                if ($type === "onSignal") {
                    $sendSignal = function (): void {
                        \posix_kill(\getmypid(), \SIGUSR1);
                    };
                    $loop->onSignal(\SIGUSR1, $fn = function ($watcherId, $signo, $i) use (&$fn, $loop, $sendSignal): void {
                        if ($i) {
                            $loop->onSignal(\SIGUSR1, $fn, --$i);
                            $loop->defer($sendSignal);
                        }
                        $loop->cancel($watcherId);
                    }, $runs);
                    $loop->defer($sendSignal);
                    $control->run();
                }
            };
            $closureMem = \memory_get_usage() - $initialMem;
            $cb($runs); /* just to set up eventual structures inside loop without counting towards memory comparison */
            \gc_collect_cycles();
            $initialMem = \memory_get_usage() - $closureMem;
            $cb($runs);
            unset($cb);

            \gc_collect_cycles();
            $endMem = \memory_get_usage();

            /* this is allowing some memory usage due to runtime caches etc., but nothing actually leaking */
            $this->assertLessThan($runs * 4, $endMem - $initialMem); // * 4, as 4 is minimal sizeof(void *)
        });
    }

    /**
     * The first number of each tuple indicates the tick in which the watcher is supposed to execute, the second digit
     * indicates the order within the tick.
     */
    public function testExecutionOrderGuarantees(): void
    {
        $this->expectOutputString("01 02 03 04 " . \str_repeat("05 ", 8) . "10 11 12 " . \str_repeat(
            "13 ",
            4
        ) . "20 " . \str_repeat("21 ", 4) . "30 40 41 ");
        $this->start(function (Driver $loop): void {
            // Wrap in extra defer, so driver creation time doesn't count for timers, as timers are driver creation
            // relative instead of last tick relative before first tick.
            $loop->defer(function () use ($loop): void {
                $f = function () use ($loop): callable {
                    $args = \func_get_args();
                    return function ($watcherId) use ($loop, &$args): void {
                        if (!$args) {
                            $this->fail("Watcher callback called too often");
                        }
                        $loop->cancel($watcherId);
                        echo \array_shift($args) . \array_shift($args), " ";
                    };
                };

                $loop->onWritable(STDOUT, $f(0, 5));
                $writ1 = $loop->onWritable(STDOUT, $f(0, 5));
                $writ2 = $loop->onWritable(STDOUT, $f(0, 5));

                $loop->delay($msDelay = 0, $f(0, 5));
                $del1 = $loop->delay($msDelay = 0, $f(0, 5));
                $del2 = $loop->delay($msDelay = 0, $f(0, 5));
                $del3 = $loop->delay($msDelay = 0, $f());
                $del4 = $loop->delay($msDelay = 0, $f(1, 3));
                $del5 = $loop->delay($msDelay = 0, $f(2, 0));
                $loop->defer(function () use ($loop, $del5): void {
                    $loop->disable($del5);
                });
                $loop->cancel($del3);
                $loop->disable($del1);
                $loop->disable($del2);

                $writ3 = $loop->onWritable(STDOUT, $f());
                $loop->cancel($writ3);
                $loop->disable($writ1);
                $loop->disable($writ2);
                $loop->enable($writ1);
                $writ4 = $loop->onWritable(STDOUT, $f(1, 3));
                $loop->onWritable(STDOUT, $f(0, 5));
                $loop->enable($writ2);
                $loop->disable($writ4);
                $loop->defer(function () use ($loop, $writ4, $f): void {
                    $loop->enable($writ4);
                    $loop->onWritable(STDOUT, $f(1, 3));
                });

                $loop->enable($del1);
                $loop->delay($msDelay = 0, $f(0, 5));
                $loop->enable($del2);
                $loop->disable($del4);
                $loop->defer(function () use ($loop, $del4, $f): void {
                    $loop->enable($del4);
                    $loop->onWritable(STDOUT, $f(1, 3));
                });

                $loop->delay($msDelay = 1000, $f(4, 1));
                $loop->delay($msDelay = 600, $f(3, 0));
                $loop->delay($msDelay = 500, $f(2, 1));
                $loop->repeat($msDelay = 500, $f(2, 1));
                $rep1 = $loop->repeat($msDelay = 250, $f(2, 1));
                $loop->disable($rep1);
                $loop->delay($msDelay = 500, $f(2, 1));
                $loop->enable($rep1);

                $loop->defer($f(0, 1));
                $def1 = $loop->defer($f(0, 3));
                $def2 = $loop->defer($f(1, 1));
                $def3 = $loop->defer($f());
                $loop->defer($f(0, 2));
                $loop->disable($def1);
                $loop->cancel($def3);
                $loop->enable($def1);
                $loop->defer(function () use ($loop, $def2, $del5, $f): void {
                    $tick = $f(0, 4);
                    $tick("invalid");
                    $loop->defer($f(1, 0));
                    $loop->enable($def2);
                    $loop->defer($f(1, 2));
                    $loop->defer(function () use ($loop, $del5, $f): void {
                        $loop->enable($del5);
                        $loop->defer(function () use ($loop, $f): void {
                            \usleep(700000); // to have $msDelay == 500 and $msDelay == 600 run at the same tick (but not $msDelay == 150)
                            $loop->defer(function () use ($loop, $f): void {
                                $loop->defer($f(4, 0));
                            });
                        });
                    });
                });
                $loop->disable($def2);
            });
        });
    }

    public function testSignalExecutionOrder(): void
    {
        $this->checkForSignalCapability();

        $this->expectOutputString("122222");
        $this->start(function (Driver $loop, DriverControl $control): void {
            $f = function ($i) use ($loop) {
                return function ($watcherId) use ($loop, $i): void {
                    $loop->cancel($watcherId);
                    echo $i;
                };
            };

            $loop->defer($f(1));
            $loop->onSignal(SIGUSR1, $f(2));
            $sig1 = $loop->onSignal(SIGUSR1, $f(2));
            $sig2 = $loop->onSignal(SIGUSR1, $f(2));
            $sig3 = $loop->onSignal(SIGUSR1, $f(" FAIL - MUST NOT BE CALLED "));
            $loop->disable($sig1);
            $loop->onSignal(SIGUSR1, $f(2));
            $loop->disable($sig2);
            $loop->enable($sig1);
            $loop->cancel($sig3);
            $loop->onSignal(SIGUSR1, $f(2));
            $loop->defer(function () use ($loop, $control, $sig2): void {
                $loop->enable($sig2);
                $loop->defer(function () use ($loop, $control): void {
                    \posix_kill(\getmypid(), \SIGUSR1);
                    $loop->delay($msDelay = 10, function () use ($control): void {
                        $control->stop();
                    });
                });
            });
        });
    }

    public function testExceptionOnEnableNonexistentWatcher(): void
    {
        $this->expectException(InvalidWatcherError::class);

        try {
            $this->loop->enable("nonexistentWatcher");
        } catch (InvalidWatcherError $e) {
            $this->assertSame("nonexistentWatcher", $e->getWatcherId());
            throw $e;
        }
    }

    public function testSuccessOnDisableNonexistentWatcher(): void
    {
        $this->loop->disable("nonexistentWatcher");

        // Otherwise risky, throwing fails the test
        $this->assertTrue(true);
    }

    public function testSuccessOnCancelNonexistentWatcher(): void
    {
        $this->loop->cancel("nonexistentWatcher");

        // Otherwise risky, throwing fails the test
        $this->assertTrue(true);
    }

    public function testExceptionOnReferenceNonexistentWatcher(): void
    {
        $this->expectException(InvalidWatcherError::class);

        try {
            $this->loop->reference("nonexistentWatcher");
        } catch (InvalidWatcherError $e) {
            $this->assertSame("nonexistentWatcher", $e->getWatcherId());
            throw $e;
        }
    }

    public function testSuccessOnUnreferenceNonexistentWatcher(): void
    {
        $this->loop->unreference("nonexistentWatcher");

        // Otherwise risky, throwing fails the test
        $this->assertTrue(true);
    }

    public function testWatcherInvalidityOnDefer(): void
    {
        $this->expectException(InvalidWatcherError::class);

        $this->start(function (Driver $loop): void {
            $loop->defer(function ($watcher) use ($loop): void {
                $loop->enable($watcher);
            });
        });
    }

    public function testWatcherInvalidityOnDelay(): void
    {
        $this->expectException(InvalidWatcherError::class);

        $this->start(function (Driver $loop): void {
            $loop->delay($msDelay = 0, function ($watcher) use ($loop): void {
                $loop->enable($watcher);
            });
        });
    }

    public function testEventsNotExecutedInSameTickAsEnabled(): void
    {
        $this->start(function (Driver $loop): void {
            $loop->defer(function () use ($loop): void {
                $loop->defer(function () use ($loop, &$diswatchers, &$watchers): void {
                    $loop->defer(function () use ($loop, $diswatchers): void {
                        foreach ($diswatchers as $watcher) {
                            $loop->disable($watcher);
                        }
                        $loop->defer(function () use ($loop, $diswatchers): void {
                            $loop->defer(function () use ($loop, $diswatchers): void {
                                foreach ($diswatchers as $watcher) {
                                    $loop->cancel($watcher);
                                }
                            });
                            foreach ($diswatchers as $watcher) {
                                $loop->enable($watcher);
                            }
                        });
                    });
                    foreach ($watchers as $watcher) {
                        $loop->cancel($watcher);
                    }
                    foreach ($diswatchers as $watcher) {
                        $loop->disable($watcher);
                        $loop->enable($watcher);
                    }
                });

                $f = function () use ($loop): array {
                    $watchers[] = $loop->defer([$this, "fail"]);
                    $watchers[] = $loop->delay($msDelay = 0, [$this, "fail"]);
                    $watchers[] = $loop->repeat($msDelay = 0, [$this, "fail"]);
                    $watchers[] = $loop->onWritable(STDIN, [$this, "fail"]);
                    return $watchers;
                };
                $watchers = $f();
                $diswatchers = $f();
            });
        });

        // Otherwise risky, as we only rely on $this->fail()
        $this->assertTrue(true);
    }

    public function testEnablingWatcherAllowsSubsequentInvocation(): void
    {
        $loop = $this->loop;
        $control = $loop->createControl();
        $increment = 0;
        $watcherId = $loop->defer(function () use (&$increment): void {
            $increment++;
        });
        $loop->disable($watcherId);
        $loop->delay($msDelay = 5, [$control, "stop"]);
        $control->run();
        $this->assertSame(0, $increment);
        $loop->enable($watcherId);
        $loop->delay($msDelay = 5, [$control, "stop"]);
        $control->run();
        $this->assertSame(1, $increment);
    }

    public function testUnresolvedEventsAreReenabledOnRunFollowingPreviousStop(): void
    {
        $increment = 0;
        $this->start(function (Driver $loop, DriverControl $control) use (&$increment): void {
            $loop->defer([$control, "stop"]);
            $control->run();

            $loop->defer(function () use (&$increment, $loop, $control): void {
                $loop->delay($msDelay = 100, function () use ($control, &$increment): void {
                    $increment++;
                    $control->stop();
                });
            });

            $this->assertSame(0, $increment);
            \usleep(5000);
        });
        $this->assertSame(1, $increment);
    }

    public function testTimerWatcherParameterOrder(): void
    {
        $this->start(function (Driver $loop, DriverControl $control): void {
            $counter = 0;
            $loop->defer(function ($watcherId) use ($control, &$counter): void {
                $this->assertIsString($watcherId);
                if (++$counter === 3) {
                    $control->stop();
                }
            });
            $loop->delay($msDelay = 5, function ($watcherId) use ($control, &$counter): void {
                $this->assertIsString($watcherId);
                if (++$counter === 3) {
                    $control->stop();
                }
            });
            $loop->repeat($msDelay = 5, function ($watcherId) use ($loop, $control, &$counter): void {
                $this->assertIsString($watcherId);
                $loop->cancel($watcherId);
                if (++$counter === 3) {
                    $control->stop();
                }
            });
        });
    }

    public function testStreamWatcherParameterOrder(): void
    {
        $this->start(function (Driver $loop) use (&$invoked): void {
            $invoked = 0;
            $loop->onWritable(STDOUT, function ($watcherId, $stream) use ($loop, &$invoked): void {
                $this->assertIsString($watcherId);
                $this->assertSame(STDOUT, $stream);
                $invoked++;
                $loop->cancel($watcherId);
            });
        });
        $this->assertSame(1, $invoked);
    }

    public function testDisablingWatcherPreventsSubsequentInvocation(): void
    {
        $this->start(function (Driver $loop, DriverControl $control): void {
            $increment = 0;
            $watcherId = $loop->defer(function () use (&$increment): void {
                $increment++;
            });

            $loop->disable($watcherId);
            $loop->delay($msDelay = 5, [$control, "stop"]);

            $this->assertSame(0, $increment);
        });
    }

    public function testImmediateExecution(): void
    {
        $loop = $this->loop;
        $increment = 0;
        $this->start(function (Driver $loop, DriverControl $control) use (&$increment): void {
            $loop->defer(function () use (&$increment): void {
                $increment++;
            });
            $loop->defer([$control, "stop"]);
        });
        $this->assertSame(1, $increment);
    }

    public function testImmediatelyCallbacksDoNotRecurseInSameTick(): void
    {
        $increment = 0;
        $this->start(function (Driver $loop, DriverControl $control) use (&$increment): void {
            $loop->defer(function () use ($loop, &$increment) {
                $increment++;
                $loop->defer(function () use (&$increment) {
                    $increment++;
                });
            });
            $loop->defer([$control, "stop"]);
        });
        $this->assertSame(1, $increment);
    }

    public function testRunExecutesEventsUntilExplicitlyStopped(): void
    {
        $increment = 0;
        $this->start(function (Driver $loop) use (&$increment): void {
            $loop->repeat($msInterval = 5, function ($watcherId) use ($loop, &$increment): void {
                $increment++;
                if ($increment === 10) {
                    $loop->cancel($watcherId);
                }
            });
        });
        $this->assertSame(10, $increment);
    }

    public function testLoopAllowsExceptionToBubbleUpDuringStart(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("loop error");

        $this->start(function (Driver $loop): void {
            $loop->defer(function (): void {
                throw new \Exception("loop error");
            });
        });
    }

    public function testLoopAllowsExceptionToBubbleUpFromRepeatingAlarmDuringStart(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("test");

        $this->start(function (Driver $loop): void {
            $loop->repeat($msInterval = 1, function (): void {
                throw new \RuntimeException("test");
            });
        });
    }

    public function testErrorHandlerCapturesUncaughtException(): void
    {
        $msg = "";
        $this->loop->setErrorHandler($f = function (): void {
        });
        $oldErrorHandler = $this->loop->setErrorHandler(function (\Exception $error) use (&$msg): void{
            $msg = $error->getMessage();
        });
        $this->assertSame($f, $oldErrorHandler);
        $this->start(function (Driver $loop) {
            $loop->defer(function () {
                throw new \Exception("loop error");
            });
        });
        $this->assertSame("loop error", $msg);
    }

    public function testOnErrorFailure(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("errorception");

        $this->loop->setErrorHandler(function (): void {
            throw new \Exception("errorception");
        });
        $this->start(function (Driver $loop): void {
            $loop->delay($msDelay = 5, function () {
                throw new \Exception("error");
            });
        });
    }

    public function testLoopException(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("test");

        $this->start(function (Driver $loop): void {
            $loop->defer(function () use ($loop): void {
                // force next tick, outside of primary startup tick
                $loop->defer(function () {
                    throw new \RuntimeException("test");
                });
            });
        });
    }

    public function testOnSignalWatcher(): void
    {
        $this->checkForSignalCapability();

        $this->expectOutputString("caught SIGUSR1");
        $this->start(function (Driver $loop, DriverControl $control): void {
            $loop->delay($msDelay = 1, function () use ($loop, $control): void {
                \posix_kill(\getmypid(), \SIGUSR1);
                $loop->delay($msDelay = 10, [$control, "stop"]);
            });

            $loop->onSignal(SIGUSR1, function ($watcherId) use ($loop): void {
                $loop->cancel($watcherId);
                echo "caught SIGUSR1";
            });
        });
    }

    public function testInitiallyDisabledOnSignalWatcher(): void
    {
        $this->checkForSignalCapability();

        $this->expectOutputString("caught SIGUSR1");
        $this->start(function (Driver $loop, DriverControl $control): void {
            $stop = $loop->delay($msDelay = 100, function () use ($control): void {
                echo "ERROR: manual stop";
                $control->stop();
            });
            $watcherId = $loop->onSignal(SIGUSR1, function ($watcherId) use ($loop, $stop): void {
                echo "caught SIGUSR1";
                $loop->disable($stop);
                $loop->disable($watcherId);
            });
            $loop->disable($watcherId);

            $loop->delay($msDelay = 1, function () use ($loop, $watcherId): void {
                $loop->enable($watcherId);
                $loop->delay($msDelay = 1, function () {
                    \posix_kill(\getmypid(), SIGUSR1);
                });
            });
        });
    }

    public function testNestedLoopSignalDispatch(): void
    {
        $this->checkForSignalCapability();

        $this->expectOutputString("inner SIGUSR2\nouter SIGUSR1\n");
        $this->start(function (Driver $loop, DriverControl $control): void {
            $loop->delay($msDelay = 300, function () use ($control): void {
                $control->stop();
            });
            $loop->onSignal(SIGUSR1, function () use ($control): void {
                echo "outer SIGUSR1\n";
                $control->stop();
            });

            $loop->delay($msDelay = 1, function (): void {
                /** @var Driver $loop */
                $loop = ($this->getFactory())();
                $control = $loop->createControl();
                $stop = $loop->delay($msDelay = 100, function () use ($control): void {
                    echo "ERROR: manual stop";
                    $control->stop();
                });
                $loop->onSignal(SIGUSR2, function ($watcherId) use ($loop, $stop): void {
                    echo "inner SIGUSR2\n";
                    $loop->cancel($stop);
                    $loop->cancel($watcherId);
                });
                $loop->delay($msDelay = 1, function (): void {
                    \posix_kill(\getmypid(), SIGUSR2);
                });
                $control->run();
            });

            $loop->delay($msDelay = 20, function (): void {
                \posix_kill(\getmypid(), \SIGUSR1);
            });
        });
    }

    public function testCancelRemovesWatcher(): void
    {
        $invoked = false;

        $this->start(function (Driver $loop, DriverControl $control) use (&$invoked): void {
            $watcherId = $loop->delay($msDelay = 10, function (): void {
                $this->fail('Watcher was not cancelled as expected');
            });

            $loop->defer(function () use ($loop, $watcherId, &$invoked): void {
                $loop->cancel($watcherId);
                $invoked = true;
            });

            $loop->delay($msDelay = 5, [$control, "stop"]);
        });

        $this->assertTrue($invoked);
    }

    public function testOnWritableWatcher(): void
    {
        $flag = false;
        $this->start(function (Driver $loop, DriverControl $control) use (&$flag): void {
            $loop->onWritable(STDOUT, function () use ($control, &$flag) {
                $flag = true;
                $control->stop();
            });
            $loop->delay($msDelay = 5, [$control, "stop"]);
        });
        $this->assertTrue($flag);
    }

    public function testInitiallyDisabledWriteWatcher(): void
    {
        $increment = 0;
        $this->start(function (Driver $loop, DriverControl $control): void {
            $watcherId = $loop->onWritable(STDOUT, function () use (&$increment): void {
                $increment++;
            });
            $loop->disable($watcherId);
            $loop->delay($msDelay = 5, [$control, "stop"]);
        });
        $this->assertSame(0, $increment);
    }

    public function testInitiallyDisabledWriteWatcherIsTriggeredOnceEnabled(): void
    {
        $this->expectOutputString("12");
        $this->start(function (Driver $loop, DriverControl $control): void {
            $watcherId = $loop->onWritable(STDOUT, function () use ($control): void {
                echo 2;
                $control->stop();
            });
            $loop->disable($watcherId);
            $loop->defer(function () use ($loop, $watcherId): void {
                $loop->enable($watcherId);
                echo 1;
            });
        });
    }

    public function testStreamWatcherDoesntSwallowExceptions(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->start(function (Driver $loop, DriverControl $control): void {
            $loop->onWritable(STDOUT, function () {
                throw new \RuntimeException;
            });
            $loop->delay($msDelay = 5, [$control, "stop"]);
        });
    }

    public function testReactorRunsUntilNoWatchersRemain(): void
    {
        $var1 = $var2 = 0;
        $this->start(function (Driver $loop) use (&$var1, &$var2): void {
            $loop->repeat($msDelay = 1, function ($watcherId) use ($loop, &$var1): void {
                if (++$var1 === 3) {
                    $loop->cancel($watcherId);
                }
            });

            $loop->onWritable(STDOUT, function ($watcherId) use ($loop, &$var2): void {
                if (++$var2 === 4) {
                    $loop->cancel($watcherId);
                }
            });
        });
        $this->assertSame(3, $var1);
        $this->assertSame(4, $var2);
    }

    public function testReactorRunsUntilNoWatchersRemainWhenStartedDeferred(): void
    {
        $var1 = $var2 = 0;
        $this->start(function (Driver $loop) use (&$var1, &$var2): void {
            $loop->defer(function () use ($loop, &$var1, &$var2): void {
                $loop->repeat($msDelay = 1, function ($watcherId) use ($loop, &$var1): void {
                    if (++$var1 === 3) {
                        $loop->cancel($watcherId);
                    }
                });

                $loop->onWritable(STDOUT, function ($watcherId) use ($loop, &$var2): void {
                    if (++$var2 === 4) {
                        $loop->cancel($watcherId);
                    }
                });
            });
        });
        $this->assertSame(3, $var1);
        $this->assertSame(4, $var2);
    }

    public function testOptionalCallbackDataPassedOnInvocation(): void
    {
        $callbackData = new \StdClass;

        $this->start(function (Driver $loop) use ($callbackData): void {
            $loop->defer(function ($watcherId, $callbackData): void {
                $callbackData->defer = true;
            }, $callbackData);
            $loop->delay($msDelay = 1, function ($watcherId, $callbackData): void {
                $callbackData->delay = true;
            }, $callbackData);
            $loop->repeat($msDelay = 1, function ($watcherId, $callbackData) use ($loop): void {
                $callbackData->repeat = true;
                $loop->cancel($watcherId);
            }, $callbackData);
            $loop->onWritable(STDERR, function ($watcherId, $stream, $callbackData) use ($loop): void {
                $callbackData->onWritable = true;
                $loop->cancel($watcherId);
            }, $callbackData);
        });

        $this->assertTrue($callbackData->defer);
        $this->assertTrue($callbackData->delay);
        $this->assertTrue($callbackData->repeat);
        $this->assertTrue($callbackData->onWritable);
    }

    public function testLoopStopPreventsTimerExecution(): void
    {
        $t = \microtime(1);
        $this->start(function (Driver $loop, DriverControl $control): void {
            $loop->defer(function () use ($loop): void {
                $loop->delay($msDelay = 1000, function (): void {
                    $this->fail("Timer was executed despite stopped loop");
                });
            });
            $loop->defer([$control, "stop"]);
        });
        $this->assertGreaterThan(\microtime(1), $t + 0.1);
    }

    public function testDeferEnabledInNextTick(): void
    {
        $tick = function () {
            $control = $this->loop->createControl();
            $this->loop->defer([$control, "stop"]);
            $control->run();
        };

        $invoked = 0;

        $watcher = $this->loop->onWritable(STDOUT, function () use (&$invoked): void {
            $invoked++;
        });

        $tick();
        $tick();
        $tick();

        $this->loop->disable($watcher);
        $this->loop->enable($watcher);
        $tick(); // disable + immediate enable after a tick should have no effect either

        $this->assertSame(4, $invoked);
    }

    // getState and setState are final, but test it here again to be sure
    public function testRegistry(): void
    {
        $control = $this->loop->createControl();

        $this->assertNull($this->loop->getState("foo"));
        $this->loop->setState("foo", NAN);
        $this->assertNan($this->loop->getState("foo"));
        $this->loop->setState("foo", "1");
        $this->assertNull($this->loop->getState("bar"));
        $this->loop->setState("baz", -INF);
        // running must not affect state
        $this->loop->defer([$control, "stop"]);
        $control->run();
        $this->assertSame(-INF, $this->loop->getState("baz"));
        $this->assertSame("1", $this->loop->getState("foo"));
    }

    /** @dataProvider provideRegistryValues */
    public function testRegistryValues($val): void
    {
        $this->loop->setState("foo", $val);
        $this->assertSame($val, $this->loop->getState("foo"));
    }

    public function provideRegistryValues(): array
    {
        return [
            ["string"],
            [0],
            [PHP_INT_MIN],
            [-1.0],
            [true],
            [false],
            [[]],
            [null],
            [new \StdClass],
        ];
    }

    public function testRethrowsFromCallbacks(): void
    {
        foreach (["onReadable", "onWritable", "defer", "delay", "repeat", "onSignal"] as $watcher) {
            $promises = [
                new Failure(new \Exception("rethrow test")),
                new RejectedReactPromise(new \Exception("rethrow test")),
                new Coroutine((function () {
                    if (false) {
                        yield;
                    }

                    throw new \Exception("rethrow test");
                })()),
                (function () {
                    if (false) {
                        yield;
                    }

                    throw new \Exception("rethrow test");
                })(),
                null,
            ];

            foreach ($promises as $promise) {
                if ($watcher === "onSignal") {
                    $this->checkForSignalCapability();
                }

                try {
                    $args = [];

                    switch ($watcher) {
                        case "onSignal":
                            $args[] = SIGUSR1;
                            break;

                        case "onWritable":
                            $args[] = STDOUT;
                            break;

                        case "onReadable":
                            $ends = \stream_socket_pair(
                                \stripos(PHP_OS, "win") === 0 ? STREAM_PF_INET : STREAM_PF_UNIX,
                                STREAM_SOCK_STREAM,
                                STREAM_IPPROTO_IP
                            );
                            \fwrite($ends[0], "trigger readability watcher");
                            $args[] = $ends[1];
                            break;

                        case "delay":
                        case "repeat":
                            $args[] = 5;
                            break;
                    }

                    if ($promise === null) {
                        $args[] = function ($watcherId) {
                            $this->loop->cancel($watcherId);
                            throw new \Exception("rethrow test");
                        };
                    } else {
                        $args[] = function ($watcherId) use ($promise) {
                            $this->loop->cancel($watcherId);
                            return $promise;
                        };
                    }

                    \call_user_func_array([$this->loop, $watcher], $args);

                    if ($watcher == "onSignal") {
                        $this->loop->delay(100, function () {
                            \posix_kill(\getmypid(), \SIGUSR1);
                        });
                    }

                    $this->loop->createControl()->run();

                    $this->fail("Didn't throw expected exception.");
                } catch (\Exception $e) {
                    $this->assertSame("rethrow test", $e->getMessage());
                }
            }
        }
    }

    public function testMultipleWatchersOnSameDescriptor(): void
    {
        $sockets = \stream_socket_pair(
            \stripos(PHP_OS, "win") === 0 ? STREAM_PF_INET : STREAM_PF_UNIX,
            STREAM_SOCK_STREAM,
            STREAM_IPPROTO_IP
        );
        \fwrite($sockets[1], "testing");

        $invoked = 0;
        $watcher1 = $this->loop->onReadable($sockets[0], function ($watcher) use (&$invoked): void {
            $invoked += 1;
            $this->loop->disable($watcher);
        });
        $watcher2 = $this->loop->onReadable($sockets[0], function ($watcher) use (&$invoked): void {
            $invoked += 10;
            $this->loop->disable($watcher);
        });
        $watcher3 = $this->loop->onWritable($sockets[0], function ($watcher) use (&$invoked): void {
            $invoked += 100;
            $this->loop->disable($watcher);
        });
        $watcher4 = $this->loop->onWritable($sockets[0], function ($watcher) use (&$invoked): void {
            $invoked += 1000;
            $this->loop->disable($watcher);
        });

        $this->loop->defer(function () use ($watcher1, $watcher3): void {
            $this->loop->delay(200, function () use ($watcher1, $watcher3): void {
                $this->loop->enable($watcher1);
                $this->loop->enable($watcher3);
            });
        });

        $control = $this->loop->createControl();
        $control->run();

        $this->assertSame(1212, $invoked);

        $this->loop->enable($watcher1);
        $this->loop->enable($watcher3);

        $this->loop->delay(100, function () use ($watcher2, $watcher4) {
            $this->loop->enable($watcher2);
            $this->loop->enable($watcher4);
        });

        $control->run();

        $this->assertSame(2323, $invoked);
    }

    public function testTimerIntervalCountedWhenNotRunning(): void
    {
        $this->loop->delay(1000, function () use (&$start): void {
            $this->assertLessThan(0.5, \microtime(true) - $start);
        });

        \usleep(600000); // 600ms instead of 500ms to allow for variations in timing.
        $start = \microtime(true);
        $this->loop->createControl()->run();
    }

    public function testShortTimerDoesNotBlockOtherTimers(): void
    {
        $this->loop->repeat(0, function (): void {
            static $i = 0;

            if (++$i === 5) {
                $this->fail("Loop continues with repeat watcher");
            }

            \usleep(2000);
        });

        $control = $this->loop->createControl();

        $this->loop->delay(2, function () use ($control): void {
            $this->assertTrue(true);
            $control->stop();
        });

        $control->run();
    }

    public function testTwoShortRepeatTimersWorkAsExpected(): void
    {
        $control = $this->loop->createControl();

        $this->loop->repeat(0, function () use (&$j, $control): void {
            static $i = 0;
            if (++$i === 5) {
                $control->stop();
            }
            $j = $i;
        });
        $this->loop->repeat(0, function () use (&$k, $control): void {
            static $i = 0;
            if (++$i === 5) {
                $control->stop();
            }
            $k = $i;
        });

        $control->run();
        $this->assertLessThan(2, \abs($j - $k));
        $this->assertNotSame(0, $j);
    }

    public function testNow(): void
    {
        $now = $this->loop->now();
        $this->loop->delay(100, function () use ($now): void {
            $now += 100;
            $new = $this->loop->now();

            // Allow a few milliseconds of inaccuracy.
            $this->assertGreaterThanOrEqual($now - 1, $new);
            $this->assertLessThanOrEqual($now + 10, $new);
        });
        $this->loop->createControl()->run();
    }

    public function testBug163ConsecutiveDelayed(): void
    {
        $emits = 3;

        $this->loop->defer(function () use (&$time, $emits) {
            $time = \microtime(true);
            for ($i = 0; $i < $emits; ++$i) {
                yield new Delayed(100);
            }
            $time = \microtime(true) - $time;
        });

        $this->loop->createControl()->run();

        $this->assertGreaterThan(100 * $emits - 1 /* 1ms grace period */, $time * 1000);
    }
}
