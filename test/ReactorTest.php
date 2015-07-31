<?php

namespace Amp\Test;

abstract class ReactorTest extends \PHPUnit_Framework_TestCase {
    public function testMultipleCallsToRunHaveNoEffect() {
        \Amp\run(function () {
            \Amp\run();
        });
    }

    public function testImmediatelyWatcherKeepAliveRunResult() {
        $invoked = false;
        \Amp\run(function () use (&$invoked) {
            \Amp\immediately(function () use (&$invoked) {
                $invoked = true;
            }, ["keep_alive" => false]);
        });
        $this->assertFalse($invoked);
    }

    public function testOnceWatcherKeepAliveRunResult() {
        $invoked = false;
        \Amp\run(function () use (&$invoked) {
            \Amp\once(function () use (&$invoked) {
                $invoked = true;
            }, 2000, $options = ["keep_alive" => false]);
        });
        $this->assertFalse($invoked);
    }

    public function testRepeatWatcherKeepAliveRunResult() {
        $invoked = false;
        \Amp\run(function () use (&$invoked) {
            \Amp\repeat(function () use (&$invoked) {
                $invoked = true;
            }, 2000, $options = ["keep_alive" => false]);
        });
        $this->assertFalse($invoked);
    }

    public function testOnReadableWatcherKeepAliveRunResult() {
        \Amp\run(function () {
            \Amp\onReadable(STDIN, function () {
                // empty
            }, $options = ["keep_alive" => false]);
        });
    }

    public function testOnWritableWatcherKeepAliveRunResult() {
        \Amp\run(function () {
            \Amp\onWritable(STDOUT, function () {
                // empty
            }, $options = ["keep_alive" => false]);
        });
    }

    public function testOnSignalWatcherKeepAliveRunResult() {
        if (!\extension_loaded("pcntl")) {
            $this->markTestSkipped("ext/pcntl required to test onSignal() registration");
        }

        \Amp\run(function () {
            \Amp\onSignal(SIGUSR1, function () {
                // empty
            }, $options = ["keep_alive" => false]);
        });
    }

    /**
     * @dataProvider provideRegistrationArgs
     */
    public function testWatcherKeepAliveRegistrationInfo($type, $args) {
        if ($type === "skip") {
            $this->markTestSkipped($args);
        } elseif ($type === "onSignal") {
            $requiresCancel = true;
        } else {
            $requiresCancel = false;
        }

        $func = '\Amp\\' . $type;
        if (substr($type, 0, 2) === "on" && $type !== "once") {
            $type = "on_" . lcfirst(substr($type, 2));
        }

        // keep_alive is the default
        $watcherId1 = \call_user_func_array($func, $args);
        $info = \Amp\info();
        $expected = ["enabled" => 1, "disabled" => 0];
        $this->assertSame($expected, $info[$type]);
        $this->assertSame(1, $info["keep_alive"]);

        // explicitly keep_alive even though it's the default setting
        $argsCopy = $args;
        $argsCopy[] = ["keep_alive" => true];
        $watcherId2 = \call_user_func_array($func, $argsCopy);
        $info = \Amp\info();
        $expected = ["enabled" => 2, "disabled" => 0];
        $this->assertSame($expected, $info[$type]);
        $this->assertSame(2, $info["keep_alive"]);

        // disabling a keep_alive watcher should decrement the count
        \Amp\disable($watcherId2);
        $info = \Amp\info();
        $this->assertSame(1, $info["keep_alive"]);

        // enabling a keep_alive watcher should increment the count
        \Amp\enable($watcherId2);
        $info = \Amp\info();
        $this->assertSame(2, $info["keep_alive"]);

        // cancelling a keep_alive watcher should decrement the count
        \Amp\cancel($watcherId2);
        $info = \Amp\info();
        $this->assertSame(1, $info["keep_alive"]);

        // keep_alive => false should leave the count untouched
        $argsCopy = $args;
        $argsCopy[] = ["keep_alive" => false];
        $watcherId2 = \call_user_func_array($func, $argsCopy);
        $info = \Amp\info();
        $expected = ["enabled" => 2, "disabled" => 0];
        $this->assertSame($expected, $info[$type]);
        $this->assertSame(1, $info["keep_alive"]);

        if ($requiresCancel) {
            \Amp\cancel($watcherId1);
            \Amp\cancel($watcherId2);
        }
    }

    public function provideRegistrationArgs() {
        $args = [
            ["immediately", [function () {}]],
            ["once",        [function () {}, 5000]],
            ["repeat",      [function () {}, 5000]],
            ["onWritable",  [\STDOUT, function () {}]],
            ["onReadable",  [\STDIN, function () {}]],
        ];

        if (\extension_loaded("pcntl")) {
            $args[] = ["onSignal",    [\SIGUSR1, function () {}]];
        } else {
            $args[] = ["skip", "ext/pcntl required to test onSignal() registration"];
        }

        return $args;
    }

    /**
     * @dataProvider provideRegistrationArgs
     */
    public function testWatcherRegistrationAndCancellationInfo($type, $args) {
        if ($type === "skip") {
            $this->markTestSkipped($args);
        }

        $func = '\Amp\\' . $type;
        if (substr($type, 0, 2) === "on" && $type !== "once") {
            $type = "on_" . lcfirst(substr($type, 2));
        }

        $watcherId = \call_user_func_array($func, $args);
        $this->assertInternalType("string", $watcherId);
        $info = \Amp\info();
        $expected = ["enabled" => 1, "disabled" => 0];
        $this->assertSame($expected, $info[$type]);

        // invoke enable() on active watcher to ensure it has no side-effects
        \Amp\enable($watcherId);
        $info = \Amp\info();
        $expected = ["enabled" => 1, "disabled" => 0];
        $this->assertSame($expected, $info[$type]);

        // invoke disable() twice to ensure it has no side-effects
        \Amp\disable($watcherId);
        \Amp\disable($watcherId);

        $info = \Amp\info();
        $expected = ["enabled" => 0, "disabled" => 1];
        $this->assertSame($expected, $info[$type]);

        \Amp\cancel($watcherId);
        $info = \Amp\info();
        $expected = ["enabled" => 0, "disabled" => 0];
        $this->assertSame($expected, $info[$type]);

        $watcherId = \call_user_func_array($func, $args);
        $info = \Amp\info();
        $expected = ["enabled" => 1, "disabled" => 0];
        $this->assertSame($expected, $info[$type]);

        \Amp\disable($watcherId);
        $info = \Amp\info();
        $expected = ["enabled" => 0, "disabled" => 1];
        $this->assertSame($expected, $info[$type]);

        \Amp\enable($watcherId);
        $info = \Amp\info();
        $expected = ["enabled" => 1, "disabled" => 0];
        $this->assertSame($expected, $info[$type]);

        \Amp\cancel($watcherId);
        $info = \Amp\info();
        $expected = ["enabled" => 0, "disabled" => 0];
        $this->assertSame($expected, $info[$type]);

        // invoke cancel() again to ensure it has no side-effects
        \Amp\cancel($watcherId);
    }

    public function testEnableHasNoEffectOnNonexistentWatcher() {
        \Amp\enable("nonexistentWatcher");
    }

    public function testDisableHasNoEffectOnNonexistentWatcher() {
        \Amp\disable("nonexistentWatcher");
    }

    public function testCancelHasNoEffectOnNonexistentWatcher() {
        \Amp\cancel("nonexistentWatcher");
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage coroutine error
     */
    public function testImmediateCoroutineResolutionError() {
        \Amp\run(function () {
            yield;
            yield new \Amp\Pause(10);
            throw new \Exception("coroutine error");
        });
    }

    public function testOnErrorCapturesUncaughtException() {
        $msg = "";
        \Amp\onError(function ($error) use (&$msg) {
            $msg = $error->getMessage();
        });
        \Amp\run(function () {
            throw new \Exception("coroutine error");
        });
        $this->assertSame("coroutine error", $msg);
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage errorception
     */
    public function testOnErrorFailure() {
        \Amp\onError(function () {
            throw new \Exception("errorception");
        });
        \Amp\run(function () {
            yield;
            yield new \Amp\Pause(10, $reactor);
            throw new \Exception("coroutine error");
        });
    }

    public function testEnablingWatcherAllowsSubsequentInvocation() {
        $increment = 0;
        $watcherId = \Amp\immediately(function () use (&$increment) {
            $increment++;
        });

        \Amp\disable($watcherId);
        \Amp\once('\Amp\stop', $msDelay = 50);

        \Amp\run();
        $this->assertEquals(0, $increment);

        \Amp\enable($watcherId);
        \Amp\once('\Amp\stop', $msDelay = 50);
        \Amp\run();

        $this->assertEquals(1, $increment);
    }

    public function testTimerWatcherParameterOrder() {
        $counter = 0;
        \Amp\immediately(function ($watcherId) use (&$counter) {
            $this->assertInternalType("string", $watcherId);
            if (++$counter === 3) {
                \Amp\stop();
            }
        });
        \Amp\once(function ($watcherId) use (&$counter) {
            $this->assertInternalType("string", $watcherId);
            if (++$counter === 3) {
                \Amp\stop();
            }
        }, $msDelay = 1);
        \Amp\repeat(function ($watcherId) use (&$counter) {
            $this->assertInternalType("string", $watcherId);
            \Amp\cancel($watcherId);
            if (++$counter === 3) {
                \Amp\stop();
            }
        }, $msDelay = 1);

        \Amp\run();
    }

    public function testStreamWatcherParameterOrder() {
        $invoked = 0;
        \Amp\onWritable(STDOUT, function ($watcherId, $stream) use (&$invoked) {
            $this->assertInternalType("string", $watcherId);
            $this->assertSame(STDOUT, $stream);
            $invoked++;
            \Amp\cancel($watcherId);
        });
        \Amp\run();
        $this->assertSame(1, $invoked);
    }

    public function testDisablingWatcherPreventsSubsequentInvocation() {
        $increment = 0;
        $watcherId = \Amp\immediately(function () use (&$increment) {
            $increment++;
        });

        \Amp\disable($watcherId);
        \Amp\once('\Amp\stop', $msDelay = 50);
        \Amp\run();

        $this->assertEquals(0, $increment);
    }

    public function testUnresolvedEventsAreReenabledOnRunFollowingPreviousStop() {
        $increment = 0;
        \Amp\once(function () use (&$increment) {
            $increment++;
            \Amp\stop();
        }, $msDelay = 150);

        \Amp\run('\Amp\stop');

        $this->assertEquals(0, $increment);
        \usleep(150000);
        \Amp\run();
        $this->assertEquals(1, $increment);
    }

    public function testImmediateExecution() {
        $increment = 0;
        \Amp\immediately(function () use (&$increment) {
            $increment++;
        });
        \Amp\tick();

        $this->assertEquals(1, $increment);
    }

    public function testImmediatelyCallbacksDontRecurseInSameTick() {
        $increment = 0;
        \Amp\immediately(function () use (&$increment) {
            $increment++;
            \Amp\immediately(function () use (&$increment) {
                $increment++;
            });
        });
        \Amp\tick();
        $this->assertEquals(1, $increment);
    }

    public function testTickExecutesReadyEvents() {
        $increment = 0;
        \Amp\immediately(function () use (&$increment) {
            $increment++;
        });
        \Amp\tick();
        $this->assertEquals(1, $increment);
    }

    public function testRunExecutesEventsUntilExplicitlyStopped() {
        $increment = 0;
        \Amp\repeat(function ($watcherId) use (&$increment) {
            $increment++;
            if ($increment === 10) {
                \Amp\cancel($watcherId);
            }
        }, $msInterval = 5);
        \Amp\run();
        $this->assertEquals(10, $increment);
    }

    /**
     * @expectedException RuntimeException
     * @expectedExceptionMessage test
     */
    public function testReactorAllowsExceptionToBubbleUpDuringTick() {
        \Amp\immediately(function () {
            throw new \RuntimeException("test");
        });
        \Amp\tick();
    }

    /**
     * @expectedException RuntimeException
     * @expectedExceptionMessage test
     */
    public function testReactorAllowsExceptionToBubbleUpDuringRun() {
        \Amp\immediately(function () {
            throw new \RuntimeException("test");
        });
        \Amp\run();
    }

    /**
     * @expectedException RuntimeException
     * @expectedExceptionMessage test
     */
    public function testReactorAllowsExceptionToBubbleUpFromRepeatingAlarmDuringRun() {
        \Amp\repeat(function () {
            throw new \RuntimeException("test");
        }, $msInterval = 0);
        \Amp\run();
    }

    public function testOnSignalWatcher() {
        if (!\extension_loaded("posix")) {
            $this->markTestSkipped(
                "ext/posix required to test onSignal() capture"
            );
        }
        $this->expectOutputString("caught SIGUSR1");
        \Amp\run(function () {
            \Amp\once(function () {
                \posix_kill(\getmypid(), \SIGUSR1);
                \Amp\once(function () {
                    \Amp\stop();
                }, 100);
            }, 1);

            \Amp\onSignal(SIGUSR1, function ($watcherId) {
                \Amp\cancel($watcherId);
                echo "caught SIGUSR1";
            });
        });
    }

    public function testInitiallyDisabledOnSignalWatcher() {
        if (!\extension_loaded("posix")) {
            $this->markTestSkipped(
                "ext/posix required to test onSignal() capture"
            );
        }
        $this->expectOutputString("caught SIGUSR1");

        \Amp\run(function () {
            $sigWatcherId = \Amp\onSignal(SIGUSR1, function () {
                echo "caught SIGUSR1";
                \Amp\stop();
            }, $options = ["enable" => false]);

            \Amp\once(function () use ($sigWatcherId) {
                \Amp\enable($sigWatcherId);
                \Amp\once(function () use ($sigWatcherId) {
                    \posix_kill(\getmypid(), \SIGUSR1);
                }, 10);
            }, 10);
        });
    }

    public function testCancelRemovesWatcher() {
        $watcherId = \Amp\once(function (){
            $this->fail('Watcher was not cancelled as expected');
        }, $msDelay = 20);

        \Amp\immediately(function () use ($watcherId) {
            \Amp\cancel($watcherId);
        });
        \Amp\once('\Amp\stop', $msDelay = 5);
        \Amp\run();
    }

    public function testOnWritableWatcher() {
        $flag = false;
        \Amp\onWritable(STDOUT, function () use (&$flag) {
            $flag = true;
            \Amp\stop();
        });
        \Amp\once('\Amp\stop', $msDelay = 50);

        \Amp\run();
        $this->assertTrue($flag);
    }

    public function testInitiallyDisabledWriteWatcher() {
        $increment = 0;
        $options = ["enable" => false];
        \Amp\onWritable(STDOUT, function () use (&$increment) {
            $increment++;
        }, $options);
        \Amp\once('\Amp\stop', $msDelay = 50);
        \Amp\run();

        $this->assertSame(0, $increment);
    }

    public function testInitiallyDisabledWriteWatcherIsTriggeredOnceEnabled() {
        $increment = 0;
        $options = ["enable" => false];
        $watcherId = \Amp\onWritable(STDOUT, function () use (&$increment) {
            $increment++;
        }, $options);
        \Amp\immediately(function () use ($watcherId) {
            \Amp\enable($watcherId);
        });

        \Amp\once('\Amp\stop', $msDelay = 250);
        \Amp\run();

        $this->assertTrue($increment > 0);
    }

    /**
     * @expectedException RuntimeException
     */
    public function testStreamWatcherDoesntSwallowExceptions() {
        \Amp\onWritable(STDOUT, function () { throw new \RuntimeException; });
        \Amp\once('\Amp\stop', $msDelay = 50);
        \Amp\run();
    }

    public function testGarbageCollection() {
        \Amp\once('\Amp\stop', $msDelay = 100);
        \Amp\run();
    }

    public function testOnStartGeneratorResolvesAutomatically() {
        $test = '';
        \Amp\run(function () use (&$test) {
            yield;
            $test = "Thus Spake Zarathustra";
            \Amp\once('\Amp\stop', 1);
        });
        $this->assertSame("Thus Spake Zarathustra", $test);
    }

    public function testImmediatelyGeneratorResolvesAutomatically() {
        $test = '';
        \Amp\immediately(function () use (&$test) {
            yield;
            $test = "The abyss will gaze back into you";
            \Amp\once('\Amp\stop', 50);
        });
        \Amp\run();
        $this->assertSame("The abyss will gaze back into you", $test);
    }

    public function testOnceGeneratorResolvesAutomatically() {
        $test = '';
        $gen = function () use (&$test) {
            yield;
            $test = "There are no facts, only interpretations.";
            \Amp\once('\Amp\stop', 50);
        };
        \Amp\once($gen, 1);
        \Amp\run();
        $this->assertSame("There are no facts, only interpretations.", $test);
    }

    public function testRepeatGeneratorResolvesAutomatically() {
        $test = '';
        $gen = function ($watcherId) use (&$test) {
            \Amp\cancel($watcherId);
            yield;
            $test = "Art is the supreme task";
            \Amp\stop();
        };
        \Amp\repeat($gen, 50);
        \Amp\run();
        $this->assertSame("Art is the supreme task", $test);
    }

    public function testOnErrorCallbackInterceptsUncaughtException() {
        $var = null;
        \Amp\onError(function ($e) use (&$var) {
            $var = $e->getMessage();
        });
        \Amp\run(function () { throw new \Exception('test'); });
        $this->assertSame('test', $var);
    }

    public function testReactorRunsUntilNoWatchersRemain() {
        $var1 = 0;
        \Amp\repeat(function ($watcherId) use (&$var1) {
            if (++$var1 === 3) {
                \Amp\cancel($watcherId);
            }
        }, 0);

        $var2 = 0;
        \Amp\onWritable(STDOUT, function ($watcherId) use (&$var2) {
            if (++$var2 === 4) {
                \Amp\cancel($watcherId);
            }
        });

        \Amp\run();

        $this->assertSame(3, $var1);
        $this->assertSame(4, $var2);
    }

    public function testReactorRunsUntilNoWatchersRemainWhenStartedImmediately() {
        $var1 = 0;
        $var2 = 0;
        \Amp\run(function () use (&$var1, &$var2) {
            \Amp\repeat(function ($watcherId) use (&$var1) {
                if (++$var1 === 3) {
                    \Amp\cancel($watcherId);
                }
            }, 0);

            \Amp\onWritable(STDOUT, function ($watcherId) use (&$var2) {
                if (++$var2 === 4) {
                    \Amp\cancel($watcherId);
                }
            });
        });

        $this->assertSame(3, $var1);
        $this->assertSame(4, $var2);
    }

    public function testOptionalCallbackDataPassedOnInvocation() {
        $callbackData = new \StdClass;
        $options = ["cb_data" => $callbackData];

        \Amp\immediately(function ($watcherId, $callbackData) {
            $callbackData->immediately = true;
        }, $options);
        \Amp\once(function ($watcherId, $callbackData) {
            $callbackData->once = true;
        }, 1, $options);
        \Amp\repeat(function ($watcherId, $callbackData) {
            $callbackData->repeat = true;
            \Amp\cancel($watcherId);
        }, 1, $options);
        \Amp\onWritable(STDERR, function ($watcherId, $stream, $callbackData) {
            $callbackData->onWritable = true;
            \Amp\cancel($watcherId);
        }, $options);
        \Amp\run();

        $this->assertTrue($callbackData->immediately);
        $this->assertTrue($callbackData->once);
        $this->assertTrue($callbackData->repeat);
        $this->assertTrue($callbackData->onWritable);
    }

    public function testOptionalRepeatWatcherDelay() {
        $invoked = false;
        \Amp\repeat(function ($watcherId) use (&$invoked) {
            $invoked = true;
            \Amp\cancel($watcherId);
        }, $msInterval = 10000, $options = ["ms_delay" => 1]);
        \Amp\once('\Amp\stop', 50);
        \Amp\run();
        $this->assertTrue($invoked);
    }

    public function testOptionalDisable() {
        $options = ["enable" => false];

        \Amp\immediately(function ($watcherId, $callbackData) {
            $this->fail("disabled watcher should not invoke callback");
        }, $options);
        \Amp\once(function ($watcherId, $callbackData) {
            $this->fail("disabled watcher should not invoke callback");
        }, 1, $options);
        \Amp\repeat(function ($watcherId, $callbackData) {
            $this->fail("disabled watcher should not invoke callback");
            \Amp\cancel($watcherId);
        }, 1, $options);
        \Amp\onWritable(STDERR, function ($watcherId, $stream, $callbackData) {
            $this->fail("disabled watcher should not invoke callback");
            \Amp\cancel($watcherId);
        }, $options);

        \Amp\run();
    }
}
