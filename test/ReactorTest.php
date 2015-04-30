<?php

namespace Amp\Test;

abstract class ReactorTest extends \PHPUnit_Framework_TestCase {
    abstract protected function getReactor();

    public function testEnablingWatcherAllowsSubsequentInvocation() {
        $reactor = $this->getReactor();
        $increment = 0;

        $watcherId = $reactor->immediately(function() use (&$increment) { $increment++; });
        $reactor->disable($watcherId);

        $reactor->once([$reactor, "stop"], $msDelay = 50);

        $reactor->run();
        $this->assertEquals(0, $increment);

        $reactor->enable($watcherId);
        $reactor->once([$reactor, "stop"], $msDelay = 50);

        $reactor->run();

        $this->assertEquals(1, $increment);
    }

    public function testTimerWatcherParameterOrder() {
        $reactor = $this->getReactor();
        $counter = 0;
        $reactor->immediately(function($reactorArg, $watcherId) use ($reactor, &$counter) {
            $this->assertSame($reactor, $reactorArg);
            if (++$counter === 3) {
                $reactor->stop();
            }
        });
        $reactor->once(function($reactorArg, $watcherId) use ($reactor, &$counter) {
            $this->assertSame($reactor, $reactorArg);
            if (++$counter === 3) {
                $reactor->stop();
            }
        }, $msDelay = 1);
        $reactor->repeat(function($reactorArg, $watcherId) use ($reactor, &$counter) {
            $this->assertSame($reactor, $reactorArg);
            $reactor->cancel($watcherId);
            if (++$counter === 3) {
                $reactor->stop();
            }
        }, $msDelay = 1);

        $reactor->run();
    }

    public function testStreamWatcherParameterOrder() {
        $reactor = $this->getReactor();
        $reactor->onWritable(STDOUT, function($reactorArg, $watcherId) use ($reactor) {
            $this->assertSame($reactor, $reactorArg);
            $this->assertTrue(is_string($watcherId));
            $reactor->stop();
        });
    }

    public function testDisablingWatcherPreventsSubsequentInvocation() {
        $reactor = $this->getReactor();
        $increment = 0;

        $watcherId = $reactor->immediately(function() use (&$increment) {
            $increment++;
        });

        $reactor->disable($watcherId);
        $reactor->once([$reactor, "stop"], $msDelay = 50);
        $reactor->run();

        $this->assertEquals(0, $increment);
    }

    public function testUnresolvedEventsAreReenabledOnRunFollowingPreviousStop() {
        $reactor = $this->getReactor();
        $increment = 0;
        $reactor->once(function($reactor) use (&$increment) {
            $increment++;
            $reactor->stop();
        }, $msDelay = 200);

        $reactor->run(function($reactor) {
            $reactor->stop();
        });

        $this->assertEquals(0, $increment);
        usleep(150000);
        $reactor->run();
        $this->assertEquals(1, $increment);
    }

    public function testImmediateExecution() {
        $reactor = $this->getReactor();

        $increment = 0;
        $reactor->immediately(function() use (&$increment) { $increment++; });
        $reactor->tick();

        $this->assertEquals(1, $increment);
    }

    public function testImmediatelyCallbacksDontRecurseInSameTick() {
        $reactor = $this->getReactor();

        $increment = 0;
        $reactor->immediately(function() use (&$increment, $reactor) {
            $increment++;
            $reactor->immediately(function() use (&$increment) {
                $increment++;
            });
        });
        $reactor->tick();

        $this->assertEquals(1, $increment);
    }

    public function testTickExecutesReadyEvents() {
        $reactor = $this->getReactor();

        $increment = 0;

        $reactor->immediately(function() use (&$increment) { $increment++; });
        $reactor->tick();

        $this->assertEquals(1, $increment);
    }

    public function testRunExecutesEventsUntilExplicitlyStopped() {
        $reactor = $this->getReactor();

        $increment = 0;

        $reactor->repeat(function() use (&$increment, $reactor) {
            if ($increment < 10) {
                $increment++;
            } else {
                $reactor->stop();
            }
        }, $msInterval = 1);

        $reactor->run();

        $this->assertEquals(10, $increment);
    }

    public function testOnceReturnsEventWatcher() {
        $reactor = $this->getReactor();

        $firstWatcherId = 'a';
        $watcherId = $reactor->once(function(){}, $delay = 0);
        $this->assertSame($firstWatcherId, $watcherId);

        $watcherId = $reactor->immediately(function(){});
        $this->assertSame(++$firstWatcherId, $watcherId);
    }

    /**
     * @expectedException RuntimeException
     * @expectedExceptionMessage test
     */
    public function testReactorAllowsExceptionToBubbleUpDuringTick() {
        $reactor = $this->getReactor();
        $reactor->immediately(function(){ throw new \RuntimeException('test'); });
        $reactor->tick();
    }

    /**
     * @expectedException RuntimeException
     * @expectedExceptionMessage test
     */
    public function testReactorAllowsExceptionToBubbleUpDuringRun() {
        $reactor = $this->getReactor();
        $reactor->immediately(function(){ throw new \RuntimeException('test'); });
        $reactor->run();
    }

    /**
     * @expectedException RuntimeException
     * @expectedExceptionMessage test
     */
    public function testReactorAllowsExceptionToBubbleUpFromRepeatingAlarmDuringRun() {
        $reactor = $this->getReactor();
        $reactor->repeat(function(){ throw new \RuntimeException('test'); }, $msInterval = 0);
        $reactor->run();
    }

    public function testRepeatReturnsEventWatcher() {
        $reactor = $this->getReactor();

        $firstWatcherId = 'a';
        $watcherId = $reactor->repeat(function(){}, $msInterval = 1000);
        $this->assertSame($firstWatcherId, $watcherId);

        $watcherId = $reactor->repeat(function(){}, $msInterval = 1000);
        $this->assertSame(++$firstWatcherId, $watcherId);
    }

    public function testCancelRemovesWatcher() {
        $reactor = $this->getReactor();

        $watcherId = $reactor->once(function(){
            $this->fail('Watcher was not cancelled as expected');
        }, $msDelay = 20);

        $reactor->immediately(function() use ($reactor, $watcherId) { $reactor->cancel($watcherId); });
        $reactor->once(function() use ($reactor) { $reactor->stop(); }, $msDelay = 5);
        $reactor->run();
    }

    public function testOnWritableWatcher() {
        $reactor = $this->getReactor();

        $flag = FALSE;

        $reactor->onWritable(STDOUT, function() use ($reactor, &$flag) {
            $flag = TRUE;
            $reactor->stop();
        });
        $reactor->once([$reactor, "stop"], $msDelay = 50);

        $reactor->run();
        $this->assertTrue($flag);
    }

    public function testInitiallyDisabledWriteWatcher() {
        $reactor = $this->getReactor();

        $increment = 0;
        $options = ["enable" => false];
        $reactor->onWritable(STDOUT, function() use (&$increment) { $increment++; }, $options);
        $reactor->once([$reactor, "stop"], $msDelay = 50);
        $reactor->run();

        $this->assertSame(0, $increment);
    }

    public function testInitiallyDisabledWriteWatcherIsTriggeredOnceEnabled() {
        $reactor = $this->getReactor();

        $increment = 0;
        $options = ["enable" => false];
        $watcherId = $reactor->onWritable(STDOUT, function() use (&$increment) { $increment++; }, $options);
        $reactor->immediately(function() use ($reactor, $watcherId) {
            $reactor->enable($watcherId);
        });

        $reactor->once([$reactor, "stop"], $msDelay = 250);
        $reactor->run();

        $this->assertTrue($increment > 0);
    }

    /**
     * @expectedException RuntimeException
     */
    public function testStreamWatcherDoesntSwallowExceptions() {
        $reactor = $this->getReactor();
        $reactor->onWritable(STDOUT, function() { throw new \RuntimeException; });
        $reactor->once([$reactor, "stop"], $msDelay = 50);
        $reactor->run();
    }

    public function testGarbageCollection() {
        $reactor = $this->getReactor();
        $reactor->once([$reactor, "stop"], $msDelay = 100);
        $reactor->run();
    }

    public function testOnStartGeneratorResolvesAutomatically() {
        $test = '';
        $this->getReactor()->run(function($reactor) use (&$test) {
            yield;
            $test = "Thus Spake Zarathustra";
            $reactor->once(function() use ($reactor) { $reactor->stop(); }, 1);
        });
        $this->assertSame("Thus Spake Zarathustra", $test);
    }

    public function testImmediatelyGeneratorResolvesAutomatically() {
        $reactor = $this->getReactor();
        $test = '';
        $reactor->immediately(function($reactor) use (&$test) {
            yield;
            $test = "The abyss will gaze back into you";
            $reactor->once(function($reactor) { $reactor->stop(); }, 50);
        });
        $reactor->run();
        $this->assertSame("The abyss will gaze back into you", $test);
    }

    public function testOnceGeneratorResolvesAutomatically() {
        $reactor = $this->getReactor();
        $test = '';
        $gen = function($reactor) use (&$test) {
            yield;
            $test = "There are no facts, only interpretations.";
            $reactor->once(function() use ($reactor) { $reactor->stop(); }, 50);
        };
        $reactor->once($gen, 1);
        $reactor->run();
        $this->assertSame("There are no facts, only interpretations.", $test);
    }

    public function testRepeatGeneratorResolvesAutomatically() {
        $reactor = $this->getReactor();
        $test = '';
        $gen = function($reactor, $watcherId) use (&$test) {
            $reactor->cancel($watcherId);
            yield;
            $test = "Art is the supreme task";
            $reactor->stop();
        };
        $reactor->repeat($gen, 50);
        $reactor->run();
        $this->assertSame("Art is the supreme task", $test);
    }

    public function testOnErrorCallbackInterceptsUncaughtException() {
        $var = null;
        $reactor = $this->getReactor();
        $reactor->onError(function($e) use (&$var) { $var = $e->getMessage(); });
        $reactor->run(function() { throw new \Exception('test'); });
        $this->assertSame('test', $var);
    }

    public function testReactorRunsUntilNoWatchersRemain() {
        $reactor = $this->getReactor();

        $var1 = 0;
        $reactor->repeat(function($reactor, $watcherId) use (&$var1) {
            if (++$var1 === 3) {
                $reactor->cancel($watcherId);
            }
        }, 0);

        $var2 = 0;
        $reactor->onWritable(STDOUT, function($reactor, $watcherId) use (&$var2) {
            if (++$var2 === 4) {
                $reactor->cancel($watcherId);
            }
        });

        $reactor->run();

        $this->assertSame(3, $var1);
        $this->assertSame(4, $var2);
    }

    public function testReactorRunsUntilNoWatchersRemainWhenStartedImmediately() {
        $reactor = $this->getReactor();

        $var1 = 0;
        $var2 = 0;
        $reactor->run(function($reactor) use (&$var1, &$var2) {
            $reactor->repeat(function($reactor, $watcherId) use (&$var1) {
                if (++$var1 === 3) {
                    $reactor->cancel($watcherId);
                }
            }, 0);

            $reactor->onWritable(STDOUT, function($reactor, $watcherId) use (&$var2) {
                if (++$var2 === 4) {
                    $reactor->cancel($watcherId);
                }
            });
        });

        $this->assertSame(3, $var1);
        $this->assertSame(4, $var2);
    }

    public function testOptionalCallbackDataPassedOnInvocation() {
        $callbackData = new \StdClass;
        $options = ["callback_data" => $callbackData];
        $reactor = $this->getReactor();
        $reactor->immediately(function($reactor, $watcherId, $callbackData) {
            $callbackData->immediately = true;
        }, $options);
        $reactor->once(function($reactor, $watcherId, $callbackData) {
            $callbackData->once = true;
        }, 1, $options);
        $reactor->repeat(function($reactor, $watcherId, $callbackData) {
            $callbackData->repeat = true;
            $reactor->cancel($watcherId);
        }, 1, $options);
        $reactor->onWritable(STDERR, function($reactor, $watcherId, $stream, $callbackData) {
            $callbackData->onWritable = true;
            $reactor->cancel($watcherId);
        }, $options);
        $reactor->run();

        $this->assertTrue($callbackData->immediately);
        $this->assertTrue($callbackData->once);
        $this->assertTrue($callbackData->repeat);
        $this->assertTrue($callbackData->onWritable);
    }

    public function testOptionalRepeatWatcherDelay() {
        $reactor = $this->getReactor();
        $watcherId = $reactor->repeat(function($reactor, $watcherId) {
            $reactor->cancel($watcherId);
        }, $msInterval = 10000, $options = ["ms_delay" => 1]);
        $startTime = time();
        $reactor->run();
        $endTime = time();
        $this->assertTrue(($endTime - $startTime) < $msInterval);
    }

    public function testOptionalDisable() {
        $reactor = $this->getReactor();
        $options = ["enable" => false];

        $reactor->immediately(function($reactor, $watcherId, $callbackData) {
            $this->fail("disabled watcher should not invoke callback");
        }, $options);
        $reactor->once(function($reactor, $watcherId, $callbackData) {
            $this->fail("disabled watcher should not invoke callback");
        }, 1, $options);
        $reactor->repeat(function($reactor, $watcherId, $callbackData) {
            $this->fail("disabled watcher should not invoke callback");
            $reactor->cancel($watcherId);
        }, 1, $options);
        $reactor->onWritable(STDERR, function($reactor, $watcherId, $stream, $callbackData) {
            $this->fail("disabled watcher should not invoke callback");
            $reactor->cancel($watcherId);
        }, $options);

        $reactor->run();
    }
}
