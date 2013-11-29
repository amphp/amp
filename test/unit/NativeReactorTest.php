<?php

use Alert\NativeReactor;

class NativeReactorTest extends PHPUnit_Framework_TestCase {

    function testImmediateExecution() {
        $reactor = new NativeReactor;

        $testIncrement = 0;

        $reactor->immediately(function() use (&$testIncrement) {
            $testIncrement++;
        });
        $reactor->tick();

        $this->assertEquals(1, $testIncrement);
    }

    function testWatcherIsNeverNotifiedIfStreamIsNeverReadable() {
        $reactor = new NativeReactor;
        $stream = STDIN;
        $increment = 0;

        $reactor->onReadable($stream, function($stream) use (&$increment) {
            $increment++;
        });

        $reactor->once(function() use ($reactor) { $reactor->stop(); }, $delay = 0.1);

        $reactor->run();

        $this->assertEquals(0, $increment);
    }

    function testTickExecutesReadyEvents() {
        $reactor = new NativeReactor;

        $testIncrement = 0;

        $reactor->immediately(function() use (&$testIncrement) {
            $testIncrement++;
        });
        $reactor->tick();

        $this->assertEquals(1, $testIncrement);
    }

    function testRunExecutesEventsUntilExplicitlyStopped() {
        $reactor = new NativeReactor;

        $testIncrement = 0;

        $reactor->repeat(function() use (&$testIncrement, $reactor) {
            if ($testIncrement < 10) {
                $testIncrement++;
            } else {
                $reactor->stop();
            }
        }, $delay = 0.001);
        $reactor->run();

        $this->assertEquals(10, $testIncrement);
    }

    function testImmediatelyReturnsWatcherId() {
        $reactor = new NativeReactor;

        $firstWatcherId = (PHP_INT_MAX * -1) + 1;
        $watcherId = $reactor->immediately(function(){});
        $this->assertSame($firstWatcherId, $watcherId);

        $watcherId = $reactor->immediately(function(){});
        $this->assertSame($firstWatcherId + 1, $watcherId);

        $watcherId = $reactor->immediately(function(){});
        $this->assertSame($firstWatcherId + 2, $watcherId);
    }

    function testOnceReturnsWatcherId() {
        $reactor = new NativeReactor;

        $firstWatcherId = (PHP_INT_MAX * -1) + 1;
        $watcherId = $reactor->once(function(){}, $delay = 0);

        $this->assertSame($firstWatcherId, $watcherId);
    }

    function testReactorDoesntSwallowOnceCallbackException() {
        $reactor = new NativeReactor;

        $reactor->repeat(function(){}, $delay = 1);
        $reactor->once(function(){ throw new Exception('test'); }, $delay = 0);

        try {
            $reactor->tick();
            $this->fail('Expected exception not thrown');
        } catch (Exception $e) {
            // woot! this is what we wanted
        }
    }

    function testScheduleReturnsWatcherId() {
        $reactor = new NativeReactor;
        $firstWatcherId = (PHP_INT_MAX * -1) + 1;
        $watcherId = $reactor->repeat(function(){}, $interval = 1);
        $this->assertSame($firstWatcherId, $watcherId);
    }

    function testImmediatelyAlarmAssignmentWhileAlreadyRunning() {
        $reactor = new NativeReactor;
        $counter = 0;
        $nestedCounter = 0;
        $watcherId = $reactor->repeat(function() use ($reactor, &$counter, &$nestedCounter) {
            $counter++;
            if ($counter === 1) {
                $reactor->immediately(function() use (&$nestedCounter) { $nestedCounter++; });
            } elseif ($counter === 3) {
                $reactor->stop();
            }
        }, $interval = 0.001);
        $reactor->run();
        $this->assertSame(3, $counter);
        $this->assertSame(1, $nestedCounter);
    }

    function testOnReadableReturnsWatcherId() {
        $reactor = new NativeReactor;

        $firstWatcherId = (PHP_INT_MAX * -1) + 1;
        $watcherId = $reactor->onReadable(STDIN, function(){});
        $this->assertSame($firstWatcherId, $watcherId);

        $watcherId = $reactor->onReadable(STDIN, function(){});
        $this->assertSame($firstWatcherId + 1, $watcherId);
    }

    function testOnWritableReturnsWatcherId() {
        $reactor = new NativeReactor;

        $firstWatcherId = (PHP_INT_MAX * -1) + 1;
        $watcherId = $reactor->onWritable(STDOUT, function(){});
        $this->assertSame($firstWatcherId, $watcherId);

        $watcherId = $reactor->onWritable(STDOUT, function(){});
        $this->assertSame($firstWatcherId + 1, $watcherId);
    }

    function testOnReadableCancellation() {
        $reactor = new NativeReactor;
        $watcherId = $reactor->onReadable(STDIN, function(){});
        $reactor->immediately(function() use ($reactor, $watcherId) { $reactor->cancel($watcherId); });
        $reactor->once(function() use ($reactor) { $reactor->stop(); }, $delay = 0.1);
        $reactor->run();
    }

    function testOnWritableCancellation() {
        $reactor = new NativeReactor;
        $watcherId = $reactor->onWritable(STDOUT, function(){});
        $reactor->immediately(function() use ($reactor, $watcherId) { $reactor->cancel($watcherId); });
        $reactor->once(function() use ($reactor) { $reactor->stop(); }, $delay = 0.1);
        $reactor->run();
    }

    function testReactorDoesntSwallowExceptionOnRecurringScheduledAlarm() {
        $reactor = new NativeReactor;

        $reactor->repeat(function(){ throw new Exception('test'); }, $interval = 0);

        try {
            $reactor->run();
            $this->fail('Expected exception not thrown');
        } catch (Exception $e) {
            // woot! this is what we wanted
        }
    }

    function testEnableDoesNothingIfWatcherIdDoesntExistOrIsAlreadyEnabled() {
        $reactor = new NativeReactor;
        $watcherId = $reactor->immediately(function() use ($reactor) { $reactor->stop(); });
        $reactor->enable($watcherId);
        $reactor->run();
    }

    function testAlarmWatcherDisable() {
        $reactor = new NativeReactor;
        $watcherId = $reactor->immediately(function() use ($reactor) { $reactor->stop(); });
        $reactor->disable($watcherId);
        $reactor->once(function() use ($reactor, $watcherId) { $reactor->enable($watcherId); }, $delay = 0.01);
        $reactor->run();
    }

    function testEnableReadableStreamWatcher() {
        $reactor = new NativeReactor;

        $increment = 0;
        $watcherId = $reactor->onReadable(STDIN, function() use (&$increment) { $increment++; });
        $reactor->disable($watcherId);
        $reactor->enable($watcherId);
        $reactor->once(function() use ($reactor) { $reactor->stop(); }, $delay = 0.01);
        $reactor->run();

        $this->assertSame(0, $increment);
    }

    function testEnableWritableStreamWatcher() {
        $reactor = new NativeReactor;

        $increment = 0;
        $watcherId = $reactor->onWritable(STDOUT, function() use (&$increment) { $increment++; });
        $reactor->disable($watcherId);
        $reactor->enable($watcherId);
        $reactor->once(function() use ($reactor) { $reactor->stop(); }, $delay = 0.01);
        $reactor->run();

        $this->assertTrue($increment > 0);
    }

    function testAlarmResortIfModifiedDuringStreamWatcherInvocation() {
        $reactor = new NativeReactor;
        $reactor->onWritable(STDOUT, function() use ($reactor) {
            $reactor->immediately(function() use ($reactor) { $reactor->stop(); });
        });
        $reactor->run();
    }

    function testCancelRemovesDisabledWatcher() {
        $reactor = new NativeReactor;
        $increment = 0;
        $watcherId = $reactor->immediately(function() use ($increment) { $increment++; });
        $reactor->disable($watcherId);
        $reactor->cancel($watcherId);
        $reactor->once(function() use ($reactor) { $reactor->stop(); }, $delay = 0.01);
        $reactor->run();

        $this->assertSame(0, $increment);
    }

    function testOnWritableDisable() {
        $reactor = new NativeReactor;

        $increment = 0;
        $watcherId = $reactor->onWritable(STDOUT, function() use (&$increment) { $increment++; });
        $reactor->disable($watcherId);
        $reactor->once(function() use ($reactor) { $reactor->stop(); }, $delay = 0.1);
        $reactor->run();

        $this->assertSame(0, $increment);
    }

    function testOnReadableDisable() {
        $reactor = new NativeReactor;

        $increment = 0;
        $watcherId = $reactor->onReadable(STDIN, function() use (&$increment) { $increment++; });
        $reactor->disable($watcherId);
        $reactor->once(function() use ($reactor) { $reactor->stop(); }, $delay = 0.1);
        $reactor->run();

        $this->assertSame(0, $increment);
    }

    function testCancellation() {
        $reactor = new NativeReactor;

        $watcherId = $reactor->once(function(){
            $this->fail('Watcher was not cancelled as expected');
        }, $delay = 0.005);

        $reactor->immediately(function() use ($reactor, $watcherId) { $reactor->cancel($watcherId); });
        $reactor->once(function() use ($reactor) { $reactor->stop(); }, $delay = 0.01);
        $reactor->run();
    }

    function testCancellationFromInsideWatcherCallback() {
        $reactor = new NativeReactor;
        $callback = function($watcherId, $reactor) {
            $reactor->cancel($watcherId);
            $reactor->stop();
        };
        $watcherId = $reactor->repeat($callback, $delay = 0.1);
        $reactor->run();
    }

    function testOnWritableWatcher() {
        $reactor = new NativeReactor;

        $flag = FALSE;

        $reactor->onWritable(STDOUT, function() use ($reactor, &$flag) {
            $flag = TRUE;
            $reactor->stop();
        });
        $reactor->once(function() use ($reactor) { $reactor->stop(); }, 0.05);

        $reactor->run();
        $this->assertTrue($flag);
    }

    function testOnReadableWatcher() {
        $reactor = new NativeReactor;
        $reactor->onWritable(STDIN, function() {});
        $reactor->once(function() use ($reactor) { $reactor->stop(); }, 0.05);
        $reactor->run();
    }

    function testInitiallyDisabledWriteWatcher() {
        $reactor = new NativeReactor;

        $increment = 0;
        $reactor->onWritable(STDOUT, function() use (&$increment) { $increment++; }, $isEnabled = FALSE);
        $reactor->once(function() use ($reactor) { $reactor->stop(); }, 0.05);
        $reactor->run();

        $this->assertSame(0, $increment);
    }

    function testInitiallyDisabledWriteWatcherIsTriggeredOnceEnabled() {
        $reactor = new NativeReactor;

        $increment = 0;
        $watcherId = $reactor->onWritable(STDOUT, function() use (&$increment) {
            $increment++;
        }, $isEnabled = FALSE);

        $reactor->once(function() use ($reactor, $watcherId) {
            $reactor->enable($watcherId);
        }, 0.01);

        $reactor->once(function() use ($reactor) { $reactor->stop(); }, 0.1);
        $reactor->run();

        $this->assertTrue($increment > 0);
    }

    function testGarbageCollection() {
        $reactor = new NativeReactor();
        $reactor->once(function() use ($reactor) { $reactor->stop(); }, 0.1);
        $reactor->run();
    }

}
