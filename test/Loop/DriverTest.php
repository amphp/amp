<?php

namespace Amp\Test\Loop;

use Amp\Coroutine;
use Amp\Failure;
use Amp\Loop;
use Amp\Loop\Driver;
use Amp\Loop\InvalidWatcherError;
use Amp\Loop\UnsupportedFeatureException;
use Amp\Success;
use PHPUnit\Framework\TestCase;
use React\Promise\RejectedPromise as RejectedReactPromise;

if (!defined("SIGUSR1")) {
    define("SIGUSR1", 30);
}
if (!defined("SIGUSR2")) {
    define("SIGUSR2", 31);
}

if (!defined("PHP_INT_MIN")) {
    define("PHP_INT_MIN", ~PHP_INT_MAX);
}

abstract class DriverTest extends TestCase {
    /**
     * The DriverFactory to run this test on
     *
     * @return callable
     */
    abstract function getFactory(): callable;

    /** @var Driver */
    public $loop;

    function setUp() {
        $this->loop = ($this->getFactory())();

        if (!$this->loop instanceof Driver) {
            $this->fail("Factory did not return a loop Driver");
        }

        // Required for error handler to work
        Loop::set($this->loop);
    }

    function start($cb) {
        $cb($this->loop);
        $this->loop->run();
    }

    function testEmptyLoop() {
        $this->assertNull($this->loop->run());
    }

    function testStopWorksEvenIfNotCurrentlyRunning() {
        $this->assertNull($this->loop->stop());
    }

    // Note: The running nesting is important for being able to continue actually still running loops (i.e. running flag set, if the driver has one) inside register_shutdown_function() for example
    function testLoopRunsCanBeConsecutiveAndNested() {
        $this->expectOutputString("123456");
        $this->start(function (Driver $loop) {
            $loop->stop();
            $loop->defer(function () use (&$run) {
                echo $run = 1;
            });
            $loop->run();
            if (!$run) {
                $this->fail("A loop stop before a run must not impact that run");
            }
            $loop->defer(function () use ($loop) {
                $loop->run();
                echo 5;
                $loop->defer(function () use ($loop) {
                    echo 6;
                    $loop->stop();
                    $loop->defer(function () {
                        $this->fail("A loop stopped at all levels must not execute further defers");
                    });
                });
                $loop->run();
            });
            $loop->defer(function () use ($loop) {
                echo 2;
                $loop->defer(function () use ($loop) {
                    echo 4;
                });
            });
            $loop->defer(function () use ($loop) {
                echo 3;
            });
        });
    }

    /** This MUST NOT have a "test" prefix, otherwise it's executed as test and marked as risky. */
    function checkForSignalCapability() {
        try {
            $watcher = $this->loop->onSignal(SIGUSR1, function () {
            });
            $this->loop->cancel($watcher);
        } catch (UnsupportedFeatureException $e) {
            $this->markTestSkipped("The loop is not capable of handling signals properly. Skipping.");
        }
    }

    function testWatcherUnrefRerefRunResult() {
        $invoked = false;
        $this->start(function (Driver $loop) use (&$invoked) {
            $watcher = $loop->defer(function () use (&$invoked) {
                $invoked = true;
            });
            $loop->unreference($watcher);
            $loop->unreference($watcher);
            $loop->reference($watcher);
        });
        $this->assertTrue($invoked);
    }

    function testDeferWatcherUnrefRunResult() {
        $invoked = false;
        $this->start(function (Driver $loop) use (&$invoked) {
            $watcher = $loop->defer(function () use (&$invoked) {
                $invoked = true;
            });
            $loop->unreference($watcher);
        });
        $this->assertFalse($invoked);
    }

    function testOnceWatcherUnrefRunResult() {
        $invoked = false;
        $this->start(function (Driver $loop) use (&$invoked) {
            $watcher = $loop->delay(2000, function () use (&$invoked) {
                $invoked = true;
            });
            $loop->unreference($watcher);
            $loop->unreference($watcher);
        });
        $this->assertFalse($invoked);
    }

    function testRepeatWatcherUnrefRunResult() {
        $invoked = false;
        $this->start(function (Driver $loop) use (&$invoked) {
            $watcher = $loop->repeat(2000, function () use (&$invoked) {
                $invoked = true;
            });
            $loop->unreference($watcher);
        });
        $this->assertFalse($invoked);
    }

    function testOnReadableWatcherUnrefRunResult() {
        $invoked = false;
        $this->start(function (Driver $loop) use (&$invoked) {
            $watcher = $loop->onReadable(STDIN, function () use (&$invoked) {
                $invoked = true;
            });
            $loop->unreference($watcher);
        });
        $this->assertFalse($invoked);
    }

    function testOnWritableWatcherKeepAliveRunResult() {
        $invoked = false;
        $this->start(function (Driver $loop) use (&$invoked) {
            $watcher = $loop->onWritable(STDOUT, function () use (&$invoked) {
                $invoked = true;
            });
            $loop->unreference($watcher);
        });
        $this->assertFalse($invoked);
    }

    function testOnSignalWatcherKeepAliveRunResult() {
        $this->checkForSignalCapability();
        $invoked = false;
        $this->start(function (Driver $loop) use (&$invoked) {
            $watcher = $loop->onSignal(SIGUSR1, function () {
                // empty
            });
            $watcher = $loop->delay(100, function () use (&$invoked, $loop, $watcher) {
                $invoked = true;
                $loop->unreference($watcher);
            });
            $loop->unreference($watcher);
        });
        $this->assertTrue($invoked);
    }

    function testUnreferencedDeferWatcherStillExecutes() {
        $invoked = false;
        $this->start(function (Driver $loop) use (&$invoked) {
            $watcher = $loop->defer(function () use (&$invoked) {
                $invoked = true;
            });
            $loop->unreference($watcher);
            $loop->defer(function () {
                // just to keep loop running
            });
        });
        $this->assertTrue($invoked);
    }

    function testLoopDoesNotBlockOnNegativeTimerExpiration() {
        $invoked = false;
        $this->start(function (Driver $loop) use (&$invoked) {
            $loop->delay(1, function () use (&$invoked) {
                $invoked = true;
            });

            usleep(1000 * 10);
        });
        $this->assertTrue($invoked);
    }

    function testDisabledDeferReenableInSubsequentTick() {
        $this->expectOutputString("123");
        $this->start(function (Driver $loop) {
            $watcherId = $loop->defer(function ($watcherId) {
                echo 3;
            });
            $loop->disable($watcherId);
            $loop->defer(function () use ($loop, $watcherId) {
                $loop->enable($watcherId);
                echo 2;
            });
            echo 1;
        });
    }


    function provideRegistrationArgs() {
        $args = [
            ["defer", [function () {
            }]],
            ["delay", [5, function () {
            }]],
            ["repeat", [5, function () {
            }]],
            ["onWritable", [\STDOUT, function () {
            }]],
            ["onReadable", [\STDIN, function () {
            }]],
            ["onSignal", [\SIGUSR1, function () {
            }]],
        ];

        return $args;
    }

    /**
     * @requires PHP 7
     * @dataProvider provideRegistrationArgs
     */
    function testWeakTypes($type, $args) {
        if ($type == "onSignal") {
            $this->checkForSignalCapability();
        }

        $this->start(function (Driver $loop) use ($type, $args, &$invoked) {
            if ($type == "onReadable") {
                $ends = stream_socket_pair(\stripos(PHP_OS, "win") === 0 ? STREAM_PF_INET : STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
                fwrite($ends[0], "trigger readability watcher");
                $args = [$ends[1]];
            } else {
                array_pop($args);
            }

            $expectedData = 20.75;
            if (substr($type, 0, 2) == "on") {
                $args[] = function ($watcherId, $arg, int $data) use ($loop, &$invoked, $expectedData) {
                    $invoked = true;
                    $this->assertSame((int) $expectedData, $data);
                    $loop->unreference($watcherId);
                };
            } else {
                $args[] = function ($watcherId, int $data) use ($loop, &$invoked, $expectedData, $type) {
                    $invoked = true;
                    $this->assertSame((int) $expectedData, $data);
                    if ($type == "repeat") {
                        $loop->unreference($watcherId);
                    }
                };
            }
            $args[] = $expectedData;
            call_user_func_array([$loop, $type], $args);

            if ($type == "onSignal") {
                $loop->defer(function () {
                    \posix_kill(\getmypid(), \SIGUSR1);
                });
            }
        });

        $this->assertTrue($invoked);
    }

    /** @dataProvider provideRegistrationArgs */
    function testDisableWithConsecutiveCancel($type, $args) {
        if ($type === "onSignal") {
            $this->checkForSignalCapability();
        }

        $invoked = false;
        $this->start(function (Driver $loop) use (&$invoked, $type, $args) {
            $func = [$loop, $type];
            $watcherId = \call_user_func_array($func, $args);
            $loop->disable($watcherId);
            $loop->defer(function () use (&$invoked, $loop, $watcherId) {
                $loop->cancel($watcherId);
                $invoked = true;
            });
            $this->assertFalse($invoked);
        });
        $this->assertTrue($invoked);
    }

    /** @dataProvider provideRegistrationArgs */
    function testWatcherReferenceInfo($type, $args) {
        if ($type === "onSignal") {
            $this->checkForSignalCapability();
        }

        $loop = $this->loop;

        $func = [$loop, $type];
        if (substr($type, 0, 2) === "on") {
            $type = "on_" . lcfirst(substr($type, 2));
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
    function testWatcherRegistrationAndCancellationInfo($type, $args) {
        if ($type === "onSignal") {
            $this->checkForSignalCapability();
        }

        $loop = $this->loop;

        $func = [$loop, $type];
        if (substr($type, 0, 2) === "on") {
            $type = "on_" . lcfirst(substr($type, 2));
        }

        $watcherId = \call_user_func_array($func, $args);
        $this->assertInternalType("string", $watcherId);
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

    /** @dataProvider provideRegistrationArgs */
    function testNoMemoryLeak($type, $args) {
        if ($this->getTestResultObject()->getCollectCodeCoverageInformation()) {
            $this->markTestSkipped("Cannot run this test with code coverage active [code coverage consumes memory which makes it impossible to rely on memory_get_usage()]");
        }

        $runs = 2000;

        if ($type === "onSignal") {
            $this->checkForSignalCapability();
        }

        $this->start(function (Driver $loop) use ($type, $args, $runs) {
            $initialMem = memory_get_usage();
            $cb = function ($runs) use ($loop, $type, $args) {
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
                    $loop->delay($msInterval = 7, function () use ($loop, $watchers) {
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
                $loop->run();
                if ($type === "defer") {
                    $loop->defer($fn = function ($watcherId, $i) use (&$fn, $loop) {
                        if ($i) {
                            $loop->defer($fn, --$i);
                        }
                    }, $runs);
                    $loop->run();
                }
                if ($type === "delay") {
                    $loop->delay($msDelay = 0, $fn = function ($watcherId, $i) use (&$fn, $loop) {
                        if ($i) {
                            $loop->delay($msDelay = 0, $fn, --$i);
                        }
                    }, $runs);
                    $loop->run();
                }
                if ($type === "repeat") {
                    $loop->repeat($msDelay = 0, $fn = function ($watcherId, $i) use (&$fn, $loop) {
                        $loop->cancel($watcherId);
                        if ($i) {
                            $loop->repeat($msDelay = 0, $fn, --$i);
                        }
                    }, $runs);
                    $loop->run();
                }
                if ($type === "onWritable") {
                    $loop->defer(function ($watcherId, $runs) use ($loop) {
                        $fn = function ($watcherId, $socket, $i) use (&$fn, $loop) {
                            $loop->cancel($watcherId);
                            if ($socket) {
                                fwrite($socket, ".");
                            }
                            if ($i) {
                                // explicitly use *different* streams with *different* resource ids
                                $ends = stream_socket_pair(\stripos(PHP_OS, "win") === 0 ? STREAM_PF_INET : STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
                                $loop->onWritable($ends[0], $fn, --$i);
                                $loop->onReadable($ends[1], function ($watcherId) use ($loop) {
                                    $loop->cancel($watcherId);
                                });
                            }
                        };
                        $fn($watcherId, null, $runs);
                    }, $runs + 1);
                    $loop->run();
                }
                if ($type === "onSignal") {
                    $sendSignal = function () {
                        \posix_kill(\getmypid(), \SIGUSR1);
                    };
                    $loop->onSignal(\SIGUSR1, $fn = function ($watcherId, $signo, $i) use (&$fn, $loop, $sendSignal) {
                        if ($i) {
                            $loop->onSignal(\SIGUSR1, $fn, --$i);
                            $loop->defer($sendSignal);
                        }
                        $loop->cancel($watcherId);
                    }, $runs);
                    $loop->defer($sendSignal);
                    $loop->run();
                }
            };
            $closureMem = memory_get_usage() - $initialMem;
            $cb($runs); /* just to set up eventual structures inside loop without counting towards memory comparison */
            gc_collect_cycles();
            $initialMem = memory_get_usage() - $closureMem;
            $cb($runs);
            unset($cb);

            gc_collect_cycles();
            $endMem = memory_get_usage();

            /* this is allowing some memory usage due to runtime caches etc., but nothing actually leaking */
            $this->assertLessThan($runs * 4, $endMem - $initialMem); // * 4, as 4 is minimal sizeof(void *)
        });
    }

    /**
     * The first number of each tuple indicates the tick in which the watcher is supposed to execute, the second digit
     * indicates the order within the tick.
     */
    function testExecutionOrderGuarantees() {
        $this->expectOutputString("01 02 03 04 " . str_repeat("05 ", 8) . "10 11 12 " . str_repeat("13 ", 4) . "20 " . str_repeat("21 ", 4) . "30 40 41 ");
        $this->start(function (Driver $loop) use (&$ticks) {
            $f = function () use ($loop) {
                $args = func_get_args();
                return function ($watcherId) use ($loop, &$args) {
                    if (!$args) {
                        $this->fail("Watcher callback called too often");
                    }
                    $loop->cancel($watcherId);
                    echo array_shift($args) . array_shift($args), " ";
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
            $loop->defer(function () use ($loop, $del5) {
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
            $loop->defer(function () use ($loop, $writ4, $f) {
                $loop->enable($writ4);
                $loop->onWritable(STDOUT, $f(1, 3));
            });

            $loop->enable($del1);
            $loop->delay($msDelay = 0, $f(0, 5));
            $loop->enable($del2);
            $loop->disable($del4);
            $loop->defer(function () use ($loop, $del4, $f) {
                $loop->enable($del4);
                $loop->onWritable(STDOUT, $f(1, 3));
            });

            $loop->delay($msDelay = 1000, $f(4, 1));
            $loop->delay($msDelay = 600, $f(3, 0));
            $loop->delay($msDelay = 500, $f(2, 1));
            $loop->repeat($msDelay = 500, $f(2, 1));
            $rep1 = $loop->repeat($msDelay = 50, $f(2, 1));
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
            $loop->defer(function () use ($loop, $def2, $del4, $del5, $f) {
                $tick = $f(0, 4);
                $tick("invalid");
                $loop->defer($f(1, 0));
                $loop->enable($def2);
                $loop->defer($f(1, 2));
                $loop->defer(function () use ($loop, $del5, $f) {
                    $loop->enable($del5);
                    $loop->defer(function () use ($loop, $f) {
                        usleep(700000); // to have $msDelay == 500 and $msDelay == 600 run at the same tick (but not $msDelay == 150)
                        $loop->defer(function () use ($loop, $f) {
                            $loop->defer($f(4, 0));
                        });
                    });
                });
            });
            $loop->disable($def2);
        });
    }

    function testSignalExecutionOrder() {
        $this->checkForSignalCapability();

        $this->expectOutputString("122222");
        $this->start(function (Driver $loop) {
            $f = function ($i) use ($loop) {
                return function ($watcherId) use ($loop, $i) {
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
            $loop->defer(function () use ($loop, $sig2) {
                $loop->enable($sig2);
                $loop->defer(function () use ($loop) {
                    \posix_kill(\getmypid(), \SIGUSR1);
                    $loop->delay($msDelay = 10, function () use ($loop) {
                        $loop->stop();
                    });
                });
            });
        });
    }

    /** @expectedException \Amp\Loop\InvalidWatcherError */
    function testExceptionOnEnableNonexistentWatcher() {
        try {
            $this->loop->enable("nonexistentWatcher");
        } catch (InvalidWatcherError $e) {
            $this->assertEquals("nonexistentWatcher", $e->getWatcherId());
            throw $e;
        }
    }

    function testSuccessOnDisableNonexistentWatcher() {
        $this->loop->disable("nonexistentWatcher");

        // Otherwise risky, throwing fails the test
        $this->assertTrue(true);
    }

    function testSuccessOnCancelNonexistentWatcher() {
        $this->loop->cancel("nonexistentWatcher");

        // Otherwise risky, throwing fails the test
        $this->assertTrue(true);
    }

    /** @expectedException \Amp\Loop\InvalidWatcherError */
    function testExceptionOnReferenceNonexistentWatcher() {
        try {
            $this->loop->reference("nonexistentWatcher");
        } catch (InvalidWatcherError $e) {
            $this->assertEquals("nonexistentWatcher", $e->getWatcherId());
            throw $e;
        }
    }

    /** @expectedException \Amp\Loop\InvalidWatcherError */
    function testExceptionOnUnreferenceNonexistentWatcher() {
        try {
            $this->loop->unreference("nonexistentWatcher");
        } catch (InvalidWatcherError $e) {
            $this->assertEquals("nonexistentWatcher", $e->getWatcherId());
            throw $e;
        }
    }

    /** @expectedException \Amp\Loop\InvalidWatcherError */
    function testWatcherInvalidityOnDefer() {
        $this->start(function (Driver $loop) {
            $loop->defer(function ($watcher) use ($loop) {
                $loop->enable($watcher);
            });
        });
    }

    /** @expectedException \Amp\Loop\InvalidWatcherError */
    function testWatcherInvalidityOnDelay() {
        $this->start(function (Driver $loop) {
            $loop->delay($msDelay = 0, function ($watcher) use ($loop) {
                $loop->enable($watcher);
            });
        });
    }

    function testEventsNotExecutedInSameTickAsEnabled() {
        $this->start(function (Driver $loop) {
            $loop->defer(function () use ($loop) {
                $loop->defer(function () use ($loop, &$diswatchers, &$watchers) {
                    $loop->defer(function () use ($loop, $diswatchers) {
                        foreach ($diswatchers as $watcher) {
                            $loop->disable($watcher);
                        }
                        $loop->defer(function () use ($loop, $diswatchers) {
                            $loop->defer(function () use ($loop, $diswatchers) {
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

                $f = function () use ($loop) {
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

    function testEnablingWatcherAllowsSubsequentInvocation() {
        $loop = $this->loop;
        $increment = 0;
        $watcherId = $loop->defer(function () use (&$increment) {
            $increment++;
        });
        $loop->disable($watcherId);
        $loop->delay($msDelay = 5, [$loop, "stop"]);
        $loop->run();
        $this->assertEquals(0, $increment);
        $loop->enable($watcherId);
        $loop->delay($msDelay = 5, [$loop, "stop"]);
        $loop->run();
        $this->assertEquals(1, $increment);
    }

    function testUnresolvedEventsAreReenabledOnRunFollowingPreviousStop() {
        $increment = 0;
        $this->start(function (Driver $loop) use (&$increment) {
            $loop->defer([$loop, "stop"]);
            $loop->run();

            $loop->defer(function () use (&$increment, $loop) {
                $loop->delay($msDelay = 100, function () use ($loop, &$increment) {
                    $increment++;
                    $loop->stop();
                });
            });

            $this->assertEquals(0, $increment);
            \usleep(5000);
        });
        $this->assertEquals(1, $increment);
    }

    function testTimerWatcherParameterOrder() {
        $this->start(function (Driver $loop) {
            $counter = 0;
            $loop->defer(function ($watcherId) use ($loop, &$counter) {
                $this->assertInternalType("string", $watcherId);
                if (++$counter === 3) {
                    $loop->stop();
                }
            });
            $loop->delay($msDelay = 5, function ($watcherId) use ($loop, &$counter) {
                $this->assertInternalType("string", $watcherId);
                if (++$counter === 3) {
                    $loop->stop();
                }
            });
            $loop->repeat($msDelay = 5, function ($watcherId) use ($loop, &$counter) {
                $this->assertInternalType("string", $watcherId);
                $loop->cancel($watcherId);
                if (++$counter === 3) {
                    $loop->stop();
                }
            });
        });
    }

    function testStreamWatcherParameterOrder() {
        $this->start(function (Driver $loop) use (&$invoked) {
            $invoked = 0;
            $loop->onWritable(STDOUT, function ($watcherId, $stream) use ($loop, &$invoked) {
                $this->assertInternalType("string", $watcherId);
                $this->assertSame(STDOUT, $stream);
                $invoked++;
                $loop->cancel($watcherId);
            });
        });
        $this->assertSame(1, $invoked);
    }

    function testDisablingWatcherPreventsSubsequentInvocation() {
        $this->start(function (Driver $loop) {
            $increment = 0;
            $watcherId = $loop->defer(function () use (&$increment) {
                $increment++;
            });

            $loop->disable($watcherId);
            $loop->delay($msDelay = 5, [$loop, "stop"]);

            $this->assertEquals(0, $increment);
        });
    }

    function testImmediateExecution() {
        $loop = $this->loop;
        $increment = 0;
        $this->start(function (Driver $loop) use (&$increment) {
            $loop->defer(function () use (&$increment) {
                $increment++;
            });
            $loop->defer([$loop, "stop"]);
        });
        $this->assertEquals(1, $increment);
    }

    function testImmediatelyCallbacksDoNotRecurseInSameTick() {
        $increment = 0;
        $this->start(function (Driver $loop) use (&$increment) {
            $loop->defer(function () use ($loop, &$increment) {
                $increment++;
                $loop->defer(function () use (&$increment) {
                    $increment++;
                });
            });
            $loop->defer([$loop, "stop"]);
        });
        $this->assertEquals(1, $increment);
    }

    function testRunExecutesEventsUntilExplicitlyStopped() {
        $increment = 0;
        $this->start(function (Driver $loop) use (&$increment) {
            $loop->repeat($msInterval = 5, function ($watcherId) use ($loop, &$increment) {
                $increment++;
                if ($increment === 10) {
                    $loop->cancel($watcherId);
                }
            });
        });
        $this->assertEquals(10, $increment);
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage loop error
     */
    function testLoopAllowsExceptionToBubbleUpDuringStart() {
        $this->start(function (Driver $loop) {
            $loop->defer(function () {
                throw new \Exception("loop error");
            });
        });
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage test
     */
    function testLoopAllowsExceptionToBubbleUpFromRepeatingAlarmDuringStart() {
        $this->start(function (Driver $loop) {
            $loop->repeat($msInterval = 1, function () {
                throw new \RuntimeException("test");
            });
        });
    }

    function testErrorHandlerCapturesUncaughtException() {
        $msg = "";
        $this->loop->setErrorHandler($f = function () {
        });
        $oldErrorHandler = $this->loop->setErrorHandler(function (\Exception $error) use (&$msg) {
            $msg = $error->getMessage();
        });
        $this->assertEquals($f, $oldErrorHandler);
        $this->start(function (Driver $loop) {
            $loop->defer(function () {
                throw new \Exception("loop error");
            });
        });
        $this->assertSame("loop error", $msg);
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage errorception
     */
    function testOnErrorFailure() {
        $this->loop->setErrorHandler(function () {
            throw new \Exception("errorception");
        });
        $this->start(function (Driver $loop) {
            $loop->delay($msDelay = 5, function () {
                throw new \Exception("error");
            });
        });
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage test
     */
    function testLoopException() {
        $this->start(function (Driver $loop) {
            $loop->defer(function () use ($loop) {
                // force next tick, outside of primary startup tick
                $loop->defer(function () {
                    throw new \RuntimeException("test");
                });
            });
        });
    }

    function testOnSignalWatcher() {
        $this->checkForSignalCapability();

        $this->expectOutputString("caught SIGUSR1");
        $this->start(function (Driver $loop) {
            $loop->delay($msDelay = 1, function () use ($loop) {
                \posix_kill(\getmypid(), \SIGUSR1);
                $loop->delay($msDelay = 10, function () use ($loop) {
                    $loop->stop();
                });
            });

            $loop->onSignal(SIGUSR1, function ($watcherId) use ($loop) {
                $loop->cancel($watcherId);
                echo "caught SIGUSR1";
            });
        });
    }

    function testInitiallyDisabledOnSignalWatcher() {
        $this->checkForSignalCapability();

        $this->expectOutputString("caught SIGUSR1");
        $this->start(function (Driver $loop) {
            $stop = $loop->delay($msDelay = 100, function () use ($loop) {
                echo "ERROR: manual stop";
                $loop->stop();
            });
            $watcherId = $loop->onSignal(SIGUSR1, function ($watcherId) use ($loop, $stop) {
                echo "caught SIGUSR1";
                $loop->disable($stop);
                $loop->disable($watcherId);
            });
            $loop->disable($watcherId);

            $loop->delay($msDelay = 1, function () use ($loop, $watcherId) {
                $loop->enable($watcherId);
                $loop->delay($msDelay = 1, function () {
                    \posix_kill(\getmypid(), SIGUSR1);
                });
            });
        });
    }

    function testNestedLoopSignalDispatch() {
        $this->checkForSignalCapability();

        $this->expectOutputString("inner SIGUSR2\nouter SIGUSR1\n");
        $this->start(function (Driver $loop) {
            $loop->delay($msDelay = 30, function () use ($loop) {
                $loop->stop();
            });
            $loop->onSignal(SIGUSR1, function () use ($loop) {
                echo "outer SIGUSR1\n";
                $loop->stop();
            });

            $loop->delay($msDelay = 1, function () {
                /** @var Driver $loop */
                $loop = ($this->getFactory())();
                $stop = $loop->delay($msDelay = 10, function () use ($loop) {
                    echo "ERROR: manual stop";
                    $loop->stop();
                });
                $loop->onSignal(SIGUSR2, function ($watcherId) use ($loop, $stop) {
                    echo "inner SIGUSR2\n";
                    $loop->cancel($stop);
                    $loop->cancel($watcherId);
                });
                $loop->delay($msDelay = 1, function () {
                    \posix_kill(\getmypid(), SIGUSR2);
                });
                $loop->run();
            });
            $loop->delay($msDelay = 20, function () {
                \posix_kill(\getmypid(), \SIGUSR1);
            });
        });
    }

    function testCancelRemovesWatcher() {
        $invoked = false;

        $this->start(function (Driver $loop) use (&$invoked) {
            $watcherId = $loop->delay($msDelay = 10, function () {
                $this->fail('Watcher was not cancelled as expected');
            });

            $loop->defer(function () use ($loop, $watcherId, &$invoked) {
                $loop->cancel($watcherId);
                $invoked = true;
            });

            $loop->delay($msDelay = 5, [$loop, "stop"]);
        });

        $this->assertTrue($invoked);
    }

    function testOnWritableWatcher() {
        $flag = false;
        $this->start(function (Driver $loop) use (&$flag) {
            $loop->onWritable(STDOUT, function () use ($loop, &$flag) {
                $flag = true;
                $loop->stop();
            });
            $loop->delay($msDelay = 5, [$loop, "stop"]);
        });
        $this->assertTrue($flag);
    }

    function testInitiallyDisabledWriteWatcher() {
        $increment = 0;
        $this->start(function (Driver $loop) {
            $watcherId = $loop->onWritable(STDOUT, function () use (&$increment) {
                $increment++;
            });
            $loop->disable($watcherId);
            $loop->delay($msDelay = 5, [$loop, "stop"]);
        });
        $this->assertSame(0, $increment);
    }

    function testInitiallyDisabledWriteWatcherIsTriggeredOnceEnabled() {
        $this->expectOutputString("12");
        $this->start(function (Driver $loop) {
            $watcherId = $loop->onWritable(STDOUT, function () use ($loop) {
                echo 2;
                $loop->stop();
            });
            $loop->disable($watcherId);
            $loop->defer(function () use ($loop, $watcherId) {
                $loop->enable($watcherId);
                echo 1;
            });
        });
    }

    /** @expectedException \RuntimeException */
    function testStreamWatcherDoesntSwallowExceptions() {
        $this->start(function (Driver $loop) {
            $loop->onWritable(STDOUT, function () {
                throw new \RuntimeException;
            });
            $loop->delay($msDelay = 5, [$loop, "stop"]);
        });
    }

    function testReactorRunsUntilNoWatchersRemain() {
        $var1 = $var2 = 0;
        $this->start(function (Driver $loop) use (&$var1, &$var2) {
            $loop->repeat($msDelay = 1, function ($watcherId) use ($loop, &$var1) {
                if (++$var1 === 3) {
                    $loop->cancel($watcherId);
                }
            });

            $loop->onWritable(STDOUT, function ($watcherId) use ($loop, &$var2) {
                if (++$var2 === 4) {
                    $loop->cancel($watcherId);
                }
            });
        });
        $this->assertSame(3, $var1);
        $this->assertSame(4, $var2);
    }

    function testReactorRunsUntilNoWatchersRemainWhenStartedDeferred() {
        $var1 = $var2 = 0;
        $this->start(function (Driver $loop) use (&$var1, &$var2) {
            $loop->defer(function () use ($loop, &$var1, &$var2) {
                $loop->repeat($msDelay = 1, function ($watcherId) use ($loop, &$var1) {
                    if (++$var1 === 3) {
                        $loop->cancel($watcherId);
                    }
                });

                $loop->onWritable(STDOUT, function ($watcherId) use ($loop, &$var2) {
                    if (++$var2 === 4) {
                        $loop->cancel($watcherId);
                    }
                });
            });
        });
        $this->assertSame(3, $var1);
        $this->assertSame(4, $var2);
    }

    function testOptionalCallbackDataPassedOnInvocation() {
        $callbackData = new \StdClass;

        $this->start(function (Driver $loop) use ($callbackData) {
            $loop->defer(function ($watcherId, $callbackData) use ($loop) {
                $callbackData->defer = true;
            }, $callbackData);
            $loop->delay($msDelay = 1, function ($watcherId, $callbackData) use ($loop) {
                $callbackData->delay = true;
            }, $callbackData);
            $loop->repeat($msDelay = 1, function ($watcherId, $callbackData) use ($loop) {
                $callbackData->repeat = true;
                $loop->cancel($watcherId);
            }, $callbackData);
            $loop->onWritable(STDERR, function ($watcherId, $stream, $callbackData) use ($loop) {
                $callbackData->onWritable = true;
                $loop->cancel($watcherId);
            }, $callbackData);
        });

        $this->assertTrue($callbackData->defer);
        $this->assertTrue($callbackData->delay);
        $this->assertTrue($callbackData->repeat);
        $this->assertTrue($callbackData->onWritable);
    }

    function testLoopStopPreventsTimerExecution() {
        $t = microtime(1);
        $this->start(function (Driver $loop) {
            $loop->delay($msDelay = 10000, function () {
                $this->fail("Timer was executed despite stopped loop");
            });
            $loop->defer(function () use ($loop) {
                $loop->stop();
            });
        });
        $this->assertTrue($t + 0.1 > microtime(1));
    }

    function testDeferEnabledInNextTick() {
        $tick = function () {
            $this->loop->defer(function () {
                $this->loop->stop();
            });
            $this->loop->run();
        };

        $invoked = 0;

        $watcher = $this->loop->onWritable(STDOUT, function () use (&$invoked) {
            $invoked++;
        });

        $tick();
        $tick();
        $tick();

        $this->loop->disable($watcher);
        $this->loop->enable($watcher);
        $tick(); // disable + immediate enable after a tick should have no effect either

        $this->assertEquals(4, $invoked);
    }

    // getState and setState are final, but test it here again to be sure
    function testRegistry() {
        $this->assertNull($this->loop->getState("foo"));
        $this->loop->setState("foo", NAN);
        $this->assertTrue(is_nan($this->loop->getState("foo")));
        $this->loop->setState("foo", "1");
        $this->assertNull($this->loop->getState("bar"));
        $this->loop->setState("baz", -INF);
        // running must not affect state
        $this->loop->defer([$this->loop, "stop"]);
        $this->loop->run();
        $this->assertSame(-INF, $this->loop->getState("baz"));
        $this->assertSame("1", $this->loop->getState("foo"));
    }

    /** @dataProvider provideRegistryValues */
    function testRegistryValues($val) {
        $this->loop->setState("foo", $val);
        $this->assertSame($val, $this->loop->getState("foo"));
    }

    function provideRegistryValues() {
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

    function testRethrowsFromCallbacks() {
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
                            $ends = stream_socket_pair(\stripos(PHP_OS, "win") === 0 ? STREAM_PF_INET : STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
                            fwrite($ends[0], "trigger readability watcher");
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

                    call_user_func_array([$this->loop, $watcher], $args);

                    if ($watcher == "onSignal") {
                        $this->loop->delay(100, function () {
                            \posix_kill(\getmypid(), \SIGUSR1);
                        });
                    }

                    $this->loop->run();

                    $this->fail("Didn't throw expected exception.");
                } catch (\Exception $e) {
                    $this->assertSame("rethrow test", $e->getMessage());
                }
            }
        }
    }
}
