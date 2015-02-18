<?php

namespace Amp\Test;

use Amp\Success;
use Amp\Failure;

abstract class ReactorTest extends \PHPUnit_Framework_TestCase {
    abstract protected function getReactor();

    public function testEnablingWatcherAllowsSubsequentInvocation() {
        $reactor = $this->getReactor();
        $increment = 0;

        $watcherId = $reactor->immediately(function() use (&$increment) { $increment++; });
        $reactor->disable($watcherId);

        $reactor->once([$reactor, 'stop'], $msDelay = 50);

        $reactor->run();
        $this->assertEquals(0, $increment);

        $reactor->enable($watcherId);
        $reactor->once([$reactor, 'stop'], $msDelay = 50);

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
        $reactor->once([$reactor, 'stop'], $msDelay = 50);
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

        $firstWatcherId = '1';
        $watcherId = $reactor->once(function(){}, $delay = 0);
        $this->assertSame($firstWatcherId, $watcherId);

        $watcherId = $reactor->immediately(function(){});
        $this->assertSame((string)($firstWatcherId + 1), $watcherId);
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

        $firstWatcherId = '1';
        $watcherId = $reactor->repeat(function(){}, $msInterval = 1000);
        $this->assertSame($firstWatcherId, $watcherId);

        $watcherId = $reactor->repeat(function(){}, $msInterval = 1000);
        $this->assertSame((string)($firstWatcherId + 1), $watcherId);
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
        $reactor->once([$reactor, 'stop'], $msDelay = 50);

        $reactor->run();
        $this->assertTrue($flag);
    }

    public function testInitiallyDisabledWriteWatcher() {
        $reactor = $this->getReactor();

        $increment = 0;
        $reactor->onWritable(STDOUT, function() use (&$increment) { $increment++; }, $isEnabled = FALSE);
        $reactor->once([$reactor, 'stop'], $msDelay = 50);
        $reactor->run();

        $this->assertSame(0, $increment);
    }

    public function testInitiallyDisabledWriteWatcherIsTriggeredOnceEnabled() {
        $reactor = $this->getReactor();

        $increment = 0;
        $watcherId = $reactor->onWritable(STDOUT, function() use (&$increment) {
            $increment++;
        }, $isEnabled = FALSE);

        $reactor->immediately(function() use ($reactor, $watcherId) {
            $reactor->enable($watcherId);
        });

        $reactor->once([$reactor, 'stop'], $msDelay = 250);
        $reactor->run();

        $this->assertTrue($increment > 0);
    }

    /**
     * @expectedException RuntimeException
     */
    public function testStreamWatcherDoesntSwallowExceptions() {
        $reactor = $this->getReactor();
        $reactor->onWritable(STDOUT, function() { throw new \RuntimeException; });
        $reactor->once([$reactor, 'stop'], $msDelay = 50);
        $reactor->run();
    }

    public function testGarbageCollection() {
        $reactor = $this->getReactor();
        $reactor->once([$reactor, 'stop'], $msDelay = 100);
        $reactor->run();
    }

    public function testOnStartGeneratorResolvesAutomatically() {
        $test = '';
        $this->getReactor()->run(function($reactor) use (&$test) {
            yield "pause" => 1;
            $test = "Thus Spake Zarathustra";
            $reactor->once(function() use ($reactor) { $reactor->stop(); }, 50);
        });
        $this->assertSame("Thus Spake Zarathustra", $test);
    }

    public function testImmediatelyGeneratorResolvesAutomatically() {
        $reactor = $this->getReactor();
        $test = '';
        $gen = function($reactor) use (&$test) {
            yield "pause" => 1;
            $test = "The abyss will gaze back into you";
            $reactor->once(function() use ($reactor) { $reactor->stop(); }, 50);
        };
        $reactor->immediately($gen);
        $reactor->run();
        $this->assertSame("The abyss will gaze back into you", $test);
    }

    public function testOnceGeneratorResolvesAutomatically() {
        $reactor = $this->getReactor();
        $test = '';
        $gen = function($reactor) use (&$test) {
            yield "pause" => 1;
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
            yield "pause" => 1;
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
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    public function testAllResolvesWithArrayOfResults() {
        $this->getReactor()->run(function($reactor) {
            $expected = ['r1' => 42, 'r2' => 41];
            $actual = (yield 'all' => [
                'r1' => 42,
                'r2' => new Success(41),
            ]);
            $this->assertSame($expected, $actual);
        });
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage zanzibar
     */
    public function testAllThrowsIfAnyIndividualPromiseFails() {
        $this->getReactor()->run(function($reactor) {
            $exception = new \RuntimeException('zanzibar');
            $promises = [
                'r1' => new Success(42),
                'r2' => new Failure($exception),
                'r3' => new Success(40),
            ];
            $results = (yield 'all' => $promises);
        });
    }

    public function testSomeReturnsArrayOfErrorsAndResults() {
        $this->getReactor()->run(function($reactor) {
            $exception = new \RuntimeException('zanzibar');
            $promises = [
                'r1' => new Success(42),
                'r2' => new Failure($exception),
                'r3' => new Success(40),
            ];
            list($errors, $results) = (yield 'some' => $promises);
            $this->assertSame(['r2' => $exception], $errors);
            $this->assertSame(['r1' => 42, 'r3' => 40], $results);
        });
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage All promises failed
     */
    public function testSomeThrowsIfNoPromisesResolveSuccessfully() {
        $this->getReactor()->run(function($reactor) {
            $promises = [
                'r1' => new Failure(new \RuntimeException),
                'r2' => new Failure(new \RuntimeException),
            ];
            list($errors, $results) = (yield 'some' => $promises);
        });
    }

    public function testResolvedValueEqualsReturnKeyYield() {
        $this->getReactor()->run(function($reactor) {
            $gen = function() {
                $a = (yield new Success(21));
                $b = (yield new Success(2));
                yield 'return' => ($a * $b);
            };

            $result = (yield 'coroutine' => $gen());
            $this->assertSame(42, $result);
        });
    }

    public function testResolutionFailuresAreThrownIntoGenerator() {
        $this->getReactor()->run(function($reactor) {
            $gen = function() {
                $a = (yield new Success(21));
                $b = 1;
                try {
                    yield new Failure(new \Exception('test'));
                    $this->fail('Code path should not be reached');
                } catch (\Exception $e) {
                    $this->assertSame('test', $e->getMessage());
                    $b = 2;
                }

                yield 'return' => ($a * $b);
            };

            $result = (yield 'coroutine' => $gen());
            $this->assertSame(42, $result);
        });
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage When in the chronicle of wasted time
     */
    public function testUncaughtGeneratorExceptionFailsResolverPromise() {
        $this->getReactor()->run(function($reactor) {
            $gen = function() {
                yield "pause" => 1;
                throw new \Exception('When in the chronicle of wasted time');
                yield "pause" => 1;
            };

            yield 'coroutine' => $gen();
        });
    }

    public function testAllCombinatorResolution() {
        $this->getReactor()->run(function($reactor) {
            $gen = function() {
                list($a, $b) = (yield 'all' => [
                    new Success(21),
                    new Success(2),
                ]);
                yield 'return' => ($a * $b);
            };

            $result = (yield 'coroutine' => $gen());
            $this->assertSame(42, $result);
        });
    }

    public function testAllCombinatorResolutionWithNonPromises() {
        $this->getReactor()->run(function($reactor) {
            $gen = function() {
                list($a, $b, $c) = (yield 'all' => [new Success(21), new Success(2), 10]);
                yield 'return' => ($a * $b * $c);
            };

            $result = (yield 'coroutine' => $gen());
            $this->assertSame(420, $result);
        });
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage When in the chronicle of wasted time
     */
    public function testAllCombinatorResolutionThrowsIfAnyOnePromiseFails() {
        $gen = function() {
            list($a, $b) = (yield 'all' => [
                new Success(21),
                new Failure(new \Exception('When in the chronicle of wasted time')),
            ]);
        };

        $this->getReactor()->run(function($reactor) use ($gen) {
            yield 'coroutine' => $gen();
        });
    }

    public function testCombinatorResolvesGeneratorInArray() {
        $this->getReactor()->run(function($reactor) {
            $gen1 = function() {
                yield 'return' => 21;
            };

            $gen2 = function() use ($gen1) {
                list($a, $b) = (yield 'all' => [
                    \Amp\coroutine($gen1(), $reactor),
                    new Success(2)
                ]);
                yield 'return' => ($a * $b);
            };

            $result = (yield 'coroutine' => $gen2());
            $this->assertSame(42, $result);
        });
    }

    public function testExplicitAllCombinatorResolution() {
        $this->getReactor()->run(function($reactor) {
            $gen = function() {
                list($a, $b, $c) = (yield 'all' => [
                    new Success(21),
                    new Success(2),
                    10
                ]);
                yield 'return' => ($a * $b * $c);
            };

            $result = (yield 'coroutine' => $gen());
            $this->assertSame(420, $result);
        });
    }

    public function testExplicitAnyCombinatorResolution() {
        $this->getReactor()->run(function($reactor) {
            $gen = function() {
                $any = (yield 'any' => [
                    'a' => new Success(21),
                    'b' => new Failure(new \Exception('test')),
                ]);
                
                yield 'return' => $any;
            };

            list($errors, $results) = (yield 'coroutine' => $gen());
            $this->assertSame('test', $errors['b']->getMessage());
            $this->assertSame(21, $results['a']);
        });
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage All promises failed
     */
    public function testExplicitSomeCombinatorResolutionFailsOnError() {
        $this->getReactor()->run(function($reactor) {
            $gen = function() {
                yield 'some' => [
                    'r1' => new Failure(new \RuntimeException),
                    'r2' => new Failure(new \RuntimeException),
                ];
            };
            yield 'coroutine' => $gen();
        });
    }

    /**
     * @expectedException \DomainException
     * @expectedExceptionMessage some yield command expects array; string yielded
     */
    public function testExplicitCombinatorResolutionFailsIfNonArrayYielded() {
        $this->getReactor()->run(function($reactor) {
            $gen = function() {
                yield 'some' => 'test';
            };
            yield 'coroutine' => $gen();
        });
    }

    public function testCallableBindYield() {
        $this->getReactor()->run(function($reactor) {
            // Register a repeating callback so the reactor run loop doesn't break
            // without our intervention.
            $repeatWatcherId = (yield 'repeat' => [function(){}, 1000]);

            $func = function() use ($repeatWatcherId) {
                yield "cancel" => $repeatWatcherId;
            };

            $boundFunc = (yield "bind" => $func);

            // Because this Generator function is bound to the reactor it should be
            // automatically resolved and our repeating watcher should be cancelled
            // allowing the reactor to stop running.
            $result = $boundFunc();
            $this->assertInstanceOf('Amp\\Promise', $result);
        });
    }
    
    public function testExplicitImmediatelyYieldResolution() {
        $this->getReactor()->run(function($reactor) {
            $gen = function() {
                $var = null;
                yield 'immediately' => function() use (&$var) { $var = 42; };
                yield 'pause' => 100; // pause for 100ms so the immediately callback executes
                yield 'return' => $var;
            };
            $result = (yield 'coroutine' => $gen());
            $this->assertSame(42, $result);
        });
    }

    public function testExplicitOnceYieldResolution() {
        $this->getReactor()->run(function($reactor) {
            $gen = function() {
                $var = null;
                yield 'once' => [function() use (&$var) { $var = 42; }, $msDelay = 1];
                yield 'pause' => 100; // pause for 100ms so the once callback executes
                yield 'return' => $var;
            };
            $result = (yield 'coroutine' => $gen());
            $this->assertSame(42, $result);
        });
    }

    public function testExplicitRepeatYieldResolution() {
        $this->getReactor()->run(function($reactor) {
            $var = null;
            $repeatFunc = function($reactor, $watcherId) use (&$var) {
                $var = 1;
                yield 'cancel' => $watcherId;
                $var++;
            };

            $gen = function() use (&$var, $repeatFunc) {
                yield 'repeat' => [$repeatFunc, $msDelay = 1];
                yield 'pause'   => 100; // pause for 100ms so we can be sure the repeat callback executes
                yield 'return' => $var;
            };

            $result = (yield 'coroutine' => $gen());
            $this->assertSame(2, $result);
        });
    }
    
    
    
    
    
    
    
    
    
    
    
}
