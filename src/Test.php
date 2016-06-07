<?php

namespace Interop\Async\Loop;

if (!defined("SIGUSR1")) {
    define("SIGUSR1", 10);
}

abstract class Test extends \PHPUnit_Framework_TestCase {
    /**
     * The DriverFactory to run this test on
     *
     * @return DriverFactory
     */
    abstract function getFactory();

    /** @var Driver */
    public $loop;

    function setUp()
    {
        $this->loop = $this->getFactory()->create();
        if (!$this->loop instanceof Driver) {
            $this->fail("Factory did not return a loop Driver");
        }
    }

    function start($cb)
    {
        $cb($this->loop);
        $this->loop->run();
    }

    function testEmptyLoop()
    {
        $this->loop->run();
    }

    function testStopWorksEvenIfNotCurrentlyRunning()
    {
        $this->loop->stop();
    }

    // Note: The running nesting is important for being able to continue actually still running loops (i.e. running flag set, if the driver has one) inside register_shutdown_function() for example
    function testLoopRunsCanBeConsecutiveAndNested()
    {
        $this->expectOutputString("123456");
        $this->start(function (Driver $loop) {
            $loop->stop();
            $loop->defer(function() use (&$run) {
                echo $run = 1;
            });
            $loop->run();
            if (!$run) {
                $this->fail("A loop stop before a run must not impact that run");
            }
            $loop->defer(function() use ($loop) {
                $loop->run();
                echo 4;
                $loop->defer(function() use ($loop) {
                    echo 6;
                    $loop->stop();
                    $loop->defer(function() {
                        $this->fail("A loop stopped at all levels must not execute further defers");
                    });
                });
            });
            $loop->defer(function() use ($loop) {
                echo 2;
                $loop->stop();
                $loop->stop();
                $loop->defer(function() use ($loop) {
                    echo 5;
                });
            });
            $loop->defer(function() use ($loop) {
                echo 3;
            });
        });
    }

    function testSignalCapability()
    {
        try {
            $watcher = $this->loop->onSignal(SIGUSR1, function() {});
            $this->loop->cancel($watcher);
        } catch (UnsupportedFeatureException $e) {
            $this->markTestSkipped("The loop is not capable of handling signals properly. Skipping.");
        }
    }

    function testWatcherUnrefRerefRunResult()
    {
        $invoked = false;
        $this->start(function(Driver $loop) use (&$invoked) {
            $watcher = $loop->defer(function() use (&$invoked) {
                $invoked = true;
            });
            $loop->unreference($watcher);
            $loop->unreference($watcher);
            $loop->reference($watcher);
        });
        $this->assertTrue($invoked);
    }

    function testDeferWatcherUnrefRunResult()
    {
        $this->start(function(Driver $loop)  {
            $watcher = $loop->defer(function() {
                $this->fail("Unreferenced defer watcher should not keep loop running");
            });
            $loop->unreference($watcher);
        });
    }

    function testOnceWatcherUnrefRunResult()
    {
        $invoked = false;
        $this->start(function(Driver $loop) use (&$invoked) {
            $watcher = $loop->delay(2000, function() use (&$invoked) {
                $invoked = true;
            });
            $loop->unreference($watcher);
            $loop->unreference($watcher);
        });
        $this->assertFalse($invoked);
    }

    function testRepeatWatcherUnrefRunResult()
    {
        $invoked = false;
        $this->start(function(Driver $loop) use (&$invoked) {
            $watcher = $loop->repeat(2000, function() use (&$invoked) {
                $invoked = true;
            });
            $loop->unreference($watcher);
        });
        $this->assertFalse($invoked);
    }

    function testOnReadableWatcherUnrefRunResult()
    {
        $this->start(function(Driver $loop) {
            $watcher = $loop->onReadable(STDIN, function() {
                // empty
            });
            $loop->unreference($watcher);
        });
    }

    function testOnWritableWatcherKeepAliveRunResult()
    {
        $this->start(function(Driver $loop) {
            $watcher = $loop->onWritable(STDOUT, function() {
                // empty
            });
            $loop->unreference($watcher);
        });
    }

    /** @depends testSignalCapability */
    function testOnSignalWatcherKeepAliveRunResult()
    {
        $this->start(function(Driver $loop) {
            $watcher = $loop->onSignal(SIGUSR1, function() {
                // empty
            });
            $loop->unreference($watcher);
        });
    }

    function testDisabledDeferReenableInSubsequentTick()
    {
        $this->expectOutputString("123");
        $this->start(function(Driver $loop) {
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


    function provideRegistrationArgs()
    {
        $args = [
            ["defer",      [function() {}]],
            ["delay",      [5, function() {}]],
            ["repeat",     [5, function() {}]],
            ["onWritable", [\STDOUT, function() {}]],
            ["onReadable", [\STDIN, function() {}]],
            ["onSignal",   [\SIGUSR1, function() {}]],
        ];

        return $args;
    }

    /** @dataProvider provideRegistrationArgs */
    function testDisableWithConsecutiveCancel($type, $args)
    {
        if ($type === "onSignal") {
            $this->testSignalCapability();
        }

        $this->start(function(Driver $loop) use ($type, $args) {
            $func = [$loop, $type];
            $watcherId = \call_user_func_array($func, $args);
            $loop->disable($watcherId);
            $loop->defer(function() use ($loop, $watcherId) {
                $loop->cancel($watcherId);
            });
        });
    }

    /** @dataProvider provideRegistrationArgs */
    function testWatcherReferenceInfo($type, $args)
    {
        if ($type === "onSignal") {
            $this->testSignalCapability();
        }

        $loop = $this->loop;

        $func = [$loop, $type];
        if (substr($type, 0, 2) === "on") {
            $type = "on_" . lcfirst(substr($type, 2));
        }

        // being referenced is the default
        $watcherId1 = \call_user_func_array($func, $args);
        $info = $loop->info();
        $expected = ["enabled" => 1, "disabled" => 0];
        $this->assertSame($expected, $info[$type]);
        $expected = ["referenced" => 1, "unreferenced" => 0];
        $this->assertSame($expected, $info["watchers"]);

        // explicitly reference() even though it's the default setting
        $argsCopy = $args;
        $watcherId2 = \call_user_func_array($func, $argsCopy);
        $loop->reference($watcherId2);
        $loop->reference($watcherId2);
        $info = $loop->info();
        $expected = ["enabled" => 2, "disabled" => 0];
        $this->assertSame($expected, $info[$type]);
        $expected = ["referenced" => 2, "unreferenced" => 0];
        $this->assertSame($expected, $info["watchers"]);

        // disabling a referenced watcher should decrement the referenced count
        $loop->disable($watcherId2);
        $loop->disable($watcherId2);
        $loop->disable($watcherId2);
        $info = $loop->info();
        $expected = ["referenced" => 1, "unreferenced" => 0];
        $this->assertSame($expected, $info["watchers"]);

        // enabling a referenced watcher should increment the referenced count
        $loop->enable($watcherId2);
        $loop->enable($watcherId2);
        $info = $loop->info();
        $expected = ["referenced" => 2, "unreferenced" => 0];
        $this->assertSame($expected, $info["watchers"]);

        // cancelling an referenced watcher should decrement the referenced count
        $loop->cancel($watcherId2);
        $info = $loop->info();
        $expected = ["referenced" => 1, "unreferenced" => 0];
        $this->assertSame($expected, $info["watchers"]);

        // unreference() should just increment unreferenced count
        $watcherId2 = \call_user_func_array($func, $args);
        $loop->unreference($watcherId2);
        $info = $loop->info();
        $expected = ["enabled" => 2, "disabled" => 0];
        $this->assertSame($expected, $info[$type]);
        $expected = ["referenced" => 1, "unreferenced" => 1];
        $this->assertSame($expected, $info["watchers"]);

        $loop->cancel($watcherId1);
        $loop->cancel($watcherId2);
    }

    /** @dataProvider provideRegistrationArgs */
    function testWatcherRegistrationAndCancellationInfo($type, $args)
    {
        if ($type === "onSignal") {
            $this->testSignalCapability();
        }

        $loop = $this->loop;

        $func = [$loop, $type];
        if (substr($type, 0, 2) === "on") {
            $type = "on_" . lcfirst(substr($type, 2));
        }

        $watcherId = \call_user_func_array($func, $args);
        $this->assertInternalType("string", $watcherId);
        $info = $loop->info();
        $expected = ["enabled" => 1, "disabled" => 0];
        $this->assertSame($expected, $info[$type]);

        // invoke enable() on active watcher to ensure it has no side-effects
        $loop->enable($watcherId);
        $info = $loop->info();
        $expected = ["enabled" => 1, "disabled" => 0];
        $this->assertSame($expected, $info[$type]);

        // invoke disable() twice to ensure it has no side-effects
        $loop->disable($watcherId);
        $loop->disable($watcherId);

        $info = $loop->info();
        $expected = ["enabled" => 0, "disabled" => 1];
        $this->assertSame($expected, $info[$type]);

        $loop->cancel($watcherId);
        $info = $loop->info();
        $expected = ["enabled" => 0, "disabled" => 0];
        $this->assertSame($expected, $info[$type]);

        $watcherId = \call_user_func_array($func, $args);
        $info = $loop->info();
        $expected = ["enabled" => 1, "disabled" => 0];
        $this->assertSame($expected, $info[$type]);

        $loop->disable($watcherId);
        $info = $loop->info();
        $expected = ["enabled" => 0, "disabled" => 1];
        $this->assertSame($expected, $info[$type]);

        $loop->enable($watcherId);
        $info = $loop->info();
        $expected = ["enabled" => 1, "disabled" => 0];
        $this->assertSame($expected, $info[$type]);

        $loop->cancel($watcherId);
        $info = $loop->info();
        $expected = ["enabled" => 0, "disabled" => 0];
        $this->assertSame($expected, $info[$type]);
    }

    /** @dataProvider provideRegistrationArgs */
    function testNoMemoryLeak($type, $args)
    {
        if ($type === "onSignal") {
            $this->testSignalCapability();
        }

        $this->start(function(Driver $loop) use ($type, $args) {
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
                    $loop->defer(function($watcherId, $runs) use ($loop) {
                        $fn = function ($watcherId, $socket, $i) use (&$fn, $loop) {
                            $loop->cancel($watcherId);
                            if ($socket) {
                                fwrite($socket, ".");
                            }
                            if ($i) {
                                // explicitly use *different* streams with *different* resource ids
                                $ends = stream_socket_pair(\stripos(PHP_OS, "win") === 0 ? STREAM_PF_INET : STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
                                $loop->onWritable($ends[0], $fn, --$i);
                                $loop->onReadable($ends[1], function($watcherId) use ($loop) {
                                    $loop->cancel($watcherId);
                                });
                            }
                        };
                        $fn($watcherId, null, $runs);
                    }, $runs + 1);
                    $loop->run();
                }
                if ($type === "onSignal") {
                    $watchers = [$loop->onSignal(\SIGUSR1, $fn = function ($watcherId, $i) use (&$fn, $loop, &$watchers) {
                        if ($i) {
                            $watchers[] = $loop->onSignal(\SIGUSR1, $fn, --$i);
                        } else {
                            foreach ($watchers as $watcher) {
                                $loop->cancel($watcher);
                            }
                        }
                    }, $runs)];
                    $loop->run();
                }
            };
            $closureMem = memory_get_usage() - $initialMem;
            $cb(10000); /* just to set up eventual structures inside loop without counting towards memory comparison */
            gc_collect_cycles();
            $initialMem = memory_get_usage() - $closureMem;
            $cb(10000);
            unset($cb);

            gc_collect_cycles();
            $endMem = memory_get_usage();

            /* this is allowing some memory usage due to runtime caches etc., but nothing actually leaking */
            $this->assertLessThan(40000, $endMem - $initialMem); // 4 * 10000, as 4 is minimal sizeof(void *)
        });
    }

    function testExecutionOrderGuarantees()
    {
        $this->expectOutputString("01 02 03 04 05 05 10 11 12 13 13 20 21 22 23 ");
        $this->start(function(Driver $loop) use (&$ticks) {
            $f = function() use ($loop) {
                $args = func_get_args();
                return function($watcherId) use ($loop, &$args) {
                    if (!$args) {
                        $this->fail("Watcher callback called too often");
                    }
                    $loop->cancel($watcherId);
                    echo array_shift($args) . array_shift($args), " ";
                };
            };
            $dep = function($i, $fn) use ($loop, &$max, &$cur) {
                $max || $max = new \SplObjectStorage;
                $cur || $cur = new \SplObjectStorage;
                $max[$fn] = max(isset($max[$fn]) ? $max[$fn] : 0, $i);
                $cur[$fn] = 0;
                return function($watcherId) use ($loop, $i, $fn, &$max, &$cur) {
                    $this->assertSame($i, $cur[$fn]);
                    if ($cur[$fn] == $max[$fn]) {
                        $fn($watcherId);
                    } else {
                        $loop->cancel($watcherId);
                    }
                    $cur[$fn] = $cur[$fn] + 1;
                };
            };

            $loop->onWritable(STDIN, $dep(0, $writ = $f(0, 5)));
            $writ1 = $loop->onWritable(STDIN, $dep(1, $writ));
            $writ2 = $loop->onWritable(STDIN, $dep(3, $writ));

            $loop->delay($msDelay = 0, $dep(0, $del = $f(0, 5)));
            $del1 = $loop->delay($msDelay = 0, $dep(1, $del));
            $del2 = $loop->delay($msDelay = 0, $dep(3, $del));
            $del3 = $loop->delay($msDelay = 0, $f());
            $del4 = $loop->delay($msDelay = 0, $f(1, 3));
            $loop->cancel($del3);
            $loop->disable($del1);
            $loop->disable($del2);

            $writ3 = $loop->onWritable(STDIN, $f());
            $loop->cancel($writ3);
            $loop->disable($writ1);
            $loop->disable($writ2);
            $loop->enable($writ1);
            $writ4 = $loop->onWritable(STDIN, $f(1, 3));
            $loop->onWritable(STDIN, $dep(2, $writ));
            $loop->enable($writ2);
            $loop->disable($writ4);
            $loop->defer(function() use ($loop, $writ4) {
                $loop->enable($writ4);
            });

            $loop->enable($del1);
            $loop->delay($msDelay = 0, $dep(2, $del));
            $loop->enable($del2);
            $loop->disable($del4);
            $loop->defer(function() use ($loop, $del4) {
                $loop->enable($del4);
            });

            $loop->delay($msDelay = 5, $f(2, 0));
            $loop->repeat($msDelay = 5, $f(2, 1));
            $rep1 = $loop->repeat($msDelay = 5, $f(2, 3));
            $loop->disable($rep1);
            $loop->delay($msDelay = 5, $f(2, 2));
            $loop->enable($rep1);

            $loop->defer($f(0, 1));
            $def1 = $loop->defer($f(0, 3));
            $def2 = $loop->defer($f(1, 1));
            $def3 = $loop->defer($f());
            $loop->defer($f(0, 2));
            $loop->disable($def1);
            $loop->cancel($def3);
            $loop->enable($def1);
            $loop->defer(function() use ($loop, $def2, $del4, $f) {
                $tick = $f(0, 4);
                $tick("invalid");
                $loop->defer($f(1, 0));
                $loop->enable($def2);
                $loop->defer($f(1, 2));
            });
            $loop->disable($def2);
        });
    }

    /** @depends testSignalCapability */
    function testSignalExecutionOrder()
    {
        if (!\extension_loaded("posix")) {
            $this->markTestSkipped("ext/posix required to test signal handlers");
        }

        $this->expectOutputString("123456");
        $this->start(function(Driver $loop) {
            $f = function($i) use ($loop) {
                return function($watcherId) use ($loop, $i) {
                    $loop->cancel($watcherId);
                    echo $i;
                };
            };

            $loop->defer($f(1));
            $loop->onSignal(SIGUSR1, $f(2));
            $sig1 = $loop->onSignal(SIGUSR1, $f(4));
            $sig2 = $loop->onSignal(SIGUSR1, $f(6));
            $sig3 = $loop->onSignal(SIGUSR1, $f(" FAIL - MUST NOT BE CALLED "));
            $loop->disable($sig1);
            $loop->onSignal(SIGUSR1, $f(3));
            $loop->disable($sig2);
            $loop->enable($sig1);
            $loop->cancel($sig3);
            $loop->onSignal(SIGUSR1, $f(5));
            $loop->defer(function() use ($loop, $sig2) {
                $loop->enable($sig2);
            });

            $loop->delay($msDelay = 1, function() use ($loop) {
                \posix_kill(\getmypid(), \SIGUSR1);
                $loop->delay($msDelay = 10, function() use ($loop) {
                    $loop->stop();
                });
            });
        });
    }

    /** @expectedException \Interop\Async\Loop\InvalidWatcherException */
    function testExceptionOnEnableNonexistentWatcher()
    {
        $this->loop->enable("nonexistentWatcher");
    }

    /** @expectedException \Interop\Async\Loop\InvalidWatcherException */
    function testExceptionOnDisableNonexistentWatcher()
    {
        $this->loop->disable("nonexistentWatcher");
    }

    function testSuccessOnCancelNonexistentWatcher()
    {
        $this->loop->cancel("nonexistentWatcher");
    }

    /** @expectedException \Interop\Async\Loop\InvalidWatcherException */
    function testExceptionOnReferenceNonexistentWatcher()
    {
        $this->loop->reference("nonexistentWatcher");
    }

    /** @expectedException \Interop\Async\Loop\InvalidWatcherException */
    function testExceptionOnUnreferenceNonexistentWatcher()
    {
        $this->loop->unreference("nonexistentWatcher");
    }

    /** @expectedException \Interop\Async\Loop\InvalidWatcherException */
    function testWatcherInvalidityOnDefer() {
        $this->start(function(Driver $loop) {
            $loop->defer(function($watcher) use ($loop) {
                $loop->disable($watcher);
            });
        });
    }

    /** @expectedException \Interop\Async\Loop\InvalidWatcherException */
    function testWatcherInvalidityOnDelay() {
        $this->start(function(Driver $loop) {
            $loop->delay($msDelay = 0, function($watcher) use ($loop) {
                $loop->disable($watcher);
            });
        });
    }

    function testEventsNotExecutedInSameTickAsEnabled()
    {
        $this->start(function (Driver $loop) {
            $loop->defer(function() use ($loop) {
                $loop->defer(function() use ($loop, &$diswatchers, &$watchers) {
                    foreach ($watchers as $watcher) {
                        $loop->cancel($watcher);
                    }
                    foreach ($diswatchers as $watcher) {
                        $loop->disable($watcher);
                        $loop->enable($watcher);
                    }
                    $loop->defer(function() use ($loop, $diswatchers) {
                        foreach ($diswatchers as $watcher) {
                            $loop->disable($watcher);
                        }
                        $loop->defer(function() use ($loop, $diswatchers) {
                            foreach ($diswatchers as $watcher) {
                                $loop->enable($watcher);
                            }
                            $loop->defer(function() use ($loop, $diswatchers) {
                                foreach ($diswatchers as $watcher) {
                                    $loop->cancel($watcher);
                                }
                            });
                        });
                    });
                });

                $f = function() use ($loop) {
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
    }

    function testEnablingWatcherAllowsSubsequentInvocation()
    {
        $loop = $this->loop;
        $increment = 0;
        $watcherId = $loop->defer(function() use (&$increment) {
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

    function testUnresolvedEventsAreReenabledOnRunFollowingPreviousStop()
    {
        $increment = 0;
        $this->start(function(Driver $loop) use (&$increment) {
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

    function testTimerWatcherParameterOrder()
    {
        $this->start(function(Driver $loop) {
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

    function testStreamWatcherParameterOrder()
    {
        $this->start(function(Driver $loop) use (&$invoked) {
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

    function testDisablingWatcherPreventsSubsequentInvocation()
    {
        $this->start(function(Driver $loop) {
            $increment = 0;
            $watcherId = $loop->defer(function () use (&$increment) {
                $increment++;
            });

            $loop->disable($watcherId);
            $loop->delay($msDelay = 5, [$loop, "stop"]);

            $this->assertEquals(0, $increment);
        });
    }

    function testImmediateExecution()
    {
        $loop = $this->loop;
        $increment = 0;
        $this->start(function(Driver $loop) use (&$increment) {
            $loop->defer(function () use (&$increment) {
                $increment++;
            });
            $loop->defer([$loop, "stop"]);
        });
        $this->assertEquals(1, $increment);
    }

    function testImmediatelyCallbacksDoNotRecurseInSameTick()
    {
        $increment = 0;
        $this->start(function(Driver $loop) use (&$increment) {
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

    function testRunExecutesEventsUntilExplicitlyStopped()
    {
        $increment = 0;
        $this->start(function(Driver $loop) use (&$increment) {
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
    function testLoopAllowsExceptionToBubbleUpDuringStart()
    {
        $this->start(function(Driver $loop) {
            $loop->defer(function() {
                throw new \Exception("loop error");
            });
        });
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage test
     */
    function testLoopAllowsExceptionToBubbleUpFromRepeatingAlarmDuringStart()
    {
        $this->start(function(Driver $loop) {
            $loop->repeat($msInterval = 1, function () {
                throw new \RuntimeException("test");
            });
        });
    }

    function testErrorHandlerCapturesUncaughtException()
    {
        $msg = "";
        $this->loop->setErrorHandler(function(\Exception $error) use (&$msg) {
            $msg = $error->getMessage();
        });
        $this->start(function(Driver $loop) {
            $loop->defer(function() {
                throw new \Exception("loop error");
            });
        });
        $this->assertSame("loop error", $msg);
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage errorception
     */
    function testOnErrorFailure()
    {
        $this->loop->setErrorHandler(function() {
            throw new \Exception("errorception");
        });
        $this->start(function(Driver $loop) {
            $loop->delay($msDelay = 5, function() {
                throw new \Exception("error");
            });
        });
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage test
     */
    function testLoopException()
    {
        $this->start(function(Driver $loop) {
            $loop->defer(function() use ($loop) {
                // force next tick, outside of primary startup tick
                $loop->defer(function() {
                    throw new \RuntimeException("test");
                });
            });
        });
    }

    /** @depends testSignalCapability */
    function testOnSignalWatcher()
    {
        if (!\extension_loaded("posix")) {
            $this->markTestSkipped("ext/posix required to test signal handlers");
        }

        $this->expectOutputString("caught SIGUSR1");
        $this->start(function(Driver $loop) {
            $loop->delay($msDelay = 1, function() use ($loop) {
                \posix_kill(\getmypid(), \SIGUSR1);
                $loop->delay($msDelay = 10, function() use ($loop) {
                    $loop->stop();
                });
            });

            $loop->onSignal(SIGUSR1, function($watcherId) use ($loop) {
                $loop->cancel($watcherId);
                echo "caught SIGUSR1";
            });
        });
    }

    /** @depends testSignalCapability */
    function testInitiallyDisabledOnSignalWatcher()
    {
        if (!\extension_loaded("posix")) {
            $this->markTestSkipped("ext/posix required to test signal handlers");
        }

        $this->expectOutputString("caught SIGUSR1");
        $this->start(function(Driver $loop) {
            $sigWatcherId = $loop->onSignal(SIGUSR1, function() use ($loop) {
                echo "caught SIGUSR1";
                $loop->stop();
            }, $options = ["enable" => false]);

            $loop->delay($msDelay = 10, function() use ($loop, $sigWatcherId) {
                $loop->enable($sigWatcherId);
                $loop->delay($msDelay = 10, function() use ($sigWatcherId) {
                    \posix_kill(\getmypid(), \SIGUSR1);
                });
            });
        });
    }

    function testCancelRemovesWatcher()
    {
        $this->start(function(Driver $loop) {
            $watcherId = $loop->delay($msDelay = 10, function () {
                $this->fail('Watcher was not cancelled as expected');
            });

            $loop->defer(function () use ($loop, $watcherId) {
                $loop->cancel($watcherId);
            });
            $loop->delay($msDelay = 5, [$loop, "stop"]);
        });
    }

    function testOnWritableWatcher()
    {
        $flag = false;
        $this->start(function(Driver $loop) use (&$flag) {
            $loop->onWritable(STDOUT, function () use ($loop, &$flag) {
                $flag = true;
                $loop->stop();
            });
            $loop->delay($msDelay = 5, [$loop, "stop"]);
        });
        $this->assertTrue($flag);
    }

    function testInitiallyDisabledWriteWatcher()
    {
        $increment = 0;
        $this->start(function(Driver $loop) {
            $watcherId = $loop->onWritable(STDOUT, function () use (&$increment) {
                $increment++;
            });
            $loop->disable($watcherId);
            $loop->delay($msDelay = 5, [$loop, "stop"]);
        });
        $this->assertSame(0, $increment);
    }

    function testInitiallyDisabledWriteWatcherIsTriggeredOnceEnabled()
    {
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
    function testStreamWatcherDoesntSwallowExceptions()
    {
        $this->start(function(Driver $loop) {
            $loop->onWritable(STDOUT, function () {
                throw new \RuntimeException;
            });
            $loop->delay($msDelay = 5, [$loop, "stop"]);
        });
    }

    function testReactorRunsUntilNoWatchersRemain()
    {
        $var1 = $var2 = 0;
        $this->start(function(Driver $loop) use (&$var1, &$var2) {
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

    function testReactorRunsUntilNoWatchersRemainWhenStartedDeferred()
    {
        $var1 = $var2 = 0;
        $this->start(function(Driver $loop) use (&$var1, &$var2) {
            $loop->defer(function() use ($loop, &$var1, &$var2) {
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

    function testOptionalCallbackDataPassedOnInvocation()
    {
        $callbackData = new \StdClass;

        $this->start(function(Driver $loop) use ($callbackData) {
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

    // implementations SHOULD use Interop\Async\Loop\Registry trait, but are not forced to, hence test it here again
    function testRegistry() {
        $this->assertNull($this->loop->fetchState("foo"));
        $this->loop->storeState("foo", NAN);
        $this->assertTrue(is_nan($this->loop->fetchState("foo")));
        $this->loop->storeState("foo", "1");
        $this->assertNull($this->loop->fetchState("bar"));
        $this->loop->storeState("baz", -INF);
        // running must not affect state
        $this->loop->defer([$this->loop, "stop"]);
        $this->loop->run();
        $this->assertSame(-INF, $this->loop->fetchState("baz"));
        $this->assertSame("1", $this->loop->fetchState("foo"));
    }

    /** @dataProvider provideRegistryValues */
    function testRegistryValues($val) {
        $this->loop->storeState("foo", $val);
        $this->assertSame($val, $this->loop->fetchState("foo"));
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
}
