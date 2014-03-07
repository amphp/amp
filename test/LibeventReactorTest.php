<?php

namespace Alert;

class LibeventReactorTest extends \PHPUnit_Framework_TestCase {

    private function skipIfMissingExtLibevent() {
        if (!extension_loaded('libevent')) {
            $this->markTestSkipped(
                'ext/libevent extension not loaded'
            );
        }
    }

    public function testEnablingWatcherAllowsSubsequentInvocation() {
        $this->skipIfMissingExtLibevent();
        $reactor = new LibeventReactor;
        $testIncrement = 0;

        $watcherId = $reactor->once(function() use (&$testIncrement) {
            $testIncrement++;
        }, $delay = 0);

        $reactor->disable($watcherId);

        $reactor->once(function() use ($reactor) {
            $reactor->stop();
        }, $delay = 0.01);

        $reactor->run();
        $this->assertEquals(0, $testIncrement);

        $reactor->enable($watcherId);
        $reactor->once(function() use ($reactor) {
            $reactor->stop();
        }, $delay = 0.01);

        $reactor->run();

        $this->assertEquals(1, $testIncrement);
    }

    public function testDisablingWatcherPreventsSubsequentInvocation() {
        $this->skipIfMissingExtLibevent();
        $reactor = new LibeventReactor;
        $testIncrement = 0;

        $watcherId = $reactor->once(function() use (&$testIncrement) {
            $testIncrement++;
        }, $delay = 0);

        $reactor->disable($watcherId);

        $reactor->once(function() use ($reactor) {
            $reactor->stop();
        }, $delay = 0.01);

        $reactor->run();
        $this->assertEquals(0, $testIncrement);
    }

    public function testUnresolvedEventsAreReenabledOnRunFollowingPreviousStop() {
        $this->skipIfMissingExtLibevent();
        $reactor = new LibeventReactor;
        $testIncrement = 0;

        $reactor->once(function() use (&$testIncrement, $reactor) {
            $testIncrement++;
            $reactor->stop();
        }, $delay = 0.1);

        $reactor->immediately(function() use ($reactor) {
            $reactor->stop();
        });

        $reactor->run();
        $this->assertEquals(0, $testIncrement);
        usleep(150000);
        $reactor->run();
        $this->assertEquals(1, $testIncrement);
    }

    public function testImmediateExecution() {
        $this->skipIfMissingExtLibevent();
        $reactor = new LibeventReactor;

        $testIncrement = 0;

        $reactor->immediately(function() use (&$testIncrement) {
            $testIncrement++;
        });
        $reactor->tick();

        $this->assertEquals(1, $testIncrement);
    }

    public function testTickExecutesReadyEvents() {
        $this->skipIfMissingExtLibevent();
        $reactor = new LibeventReactor;

        $testIncrement = 0;

        $reactor->once(function() use (&$testIncrement) {
            $testIncrement++;
        }, $delay = 0);
        $reactor->tick();

        $this->assertEquals(1, $testIncrement);
    }

    public function testRunExecutesEventsUntilExplicitlyStopped() {
        $this->skipIfMissingExtLibevent();
        $reactor = new LibeventReactor;

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

    public function testOnceReturnsEventWatcher() {
        $this->skipIfMissingExtLibevent();
        $reactor = new LibeventReactor;

        $firstWatcherId = (PHP_INT_MAX * -1) + 1;
        $watcherId = $reactor->once(function(){}, $delay = 0);
        $this->assertSame($firstWatcherId, $watcherId);

        $watcherId = $reactor->once(function(){}, $delay = 0);
        $this->assertSame($firstWatcherId + 1, $watcherId);
    }

    /**
     * @expectedException RuntimeException
     * @expectedExceptionMessage test
     */
    public function testReactorAllowsExceptionToBubbleUpDuringTick() {
        $this->skipIfMissingExtLibevent();
        $reactor = new LibeventReactor;
        $reactor->once(function(){ throw new \RuntimeException('test'); }, $delay = 0);
        $reactor->tick();
    }

    /**
     * @expectedException RuntimeException
     * @expectedExceptionMessage test
     */
    public function testReactorAllowsExceptionToBubbleUpDuringRun() {
        $this->skipIfMissingExtLibevent();
        $reactor = new LibeventReactor;
        $reactor->once(function(){ throw new \RuntimeException('test'); }, $delay = 0);
        $reactor->run();
    }

    /**
     * @expectedException RuntimeException
     * @expectedExceptionMessage test
     */
    public function testReactorAllowsExceptionToBubbleUpFromRepeatingAlarmDuringRun() {
        $this->skipIfMissingExtLibevent();
        $reactor = new LibeventReactor;
        $reactor->repeat(function(){ throw new \RuntimeException('test'); }, $interval = 0);
        $reactor->run();
    }

    public function testRepeatReturnsEventWatcher() {
        $this->skipIfMissingExtLibevent();
        $reactor = new LibeventReactor;

        $firstWatcherId = (PHP_INT_MAX * -1) + 1;
        $watcherId = $reactor->repeat(function(){}, $interval = 1);

        $this->assertSame($firstWatcherId, $watcherId);
    }

    public function testCancelRemovesWatcher() {
        $this->skipIfMissingExtLibevent();
        $reactor = new LibeventReactor;

        $watcherId = $reactor->once(function(){
            $this->fail('Watcher was not cancelled as expected');
        }, $delay = 0.001);

        $reactor->once(function() use ($reactor, $watcherId) { $reactor->cancel($watcherId); }, $delay = 0);
        $reactor->once(function() use ($reactor) { $reactor->stop(); }, $delay = 0.002);
        $reactor->run();
    }

    public function testOnWritableWatcher() {
        $this->skipIfMissingExtLibevent();
        $reactor = new LibeventReactor;

        $flag = FALSE;

        $reactor->onWritable(STDOUT, function() use ($reactor, &$flag) {
            $flag = TRUE;
            $reactor->stop();
        });
        $reactor->once(function() use ($reactor) { $reactor->stop(); }, 0.05);

        $reactor->run();
        $this->assertTrue($flag);
    }

    public function testInitiallyDisabledWriteWatcher() {
        $this->skipIfMissingExtLibevent();
        $reactor = new LibeventReactor;

        $increment = 0;
        $reactor->onWritable(STDOUT, function() use (&$increment) { $increment++; }, $isEnabled = FALSE);
        $reactor->once(function() use ($reactor) { $reactor->stop(); }, 0.05);
        $reactor->run();

        $this->assertSame(0, $increment);
    }

    public function testInitiallyDisabledWriteWatcherIsTriggeredOnceEnabled() {
        $this->skipIfMissingExtLibevent();
        $reactor = new LibeventReactor;

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

    /**
     * @expectedException RuntimeException
     */
    public function testStreamWatcherDoesntSwallowExceptions() {
        $this->skipIfMissingExtLibevent();
        $reactor = new LibeventReactor;

        $reactor->onWritable(STDOUT, function() { throw new \RuntimeException; });
        $reactor->once(function() use ($reactor) { $reactor->stop(); }, 0.05);
        $reactor->run();
    }

    public function testGarbageCollection() {
        $this->skipIfMissingExtLibevent();

        $reactor = new LibeventReactor();
        $reactor->once(function() use ($reactor) { $reactor->stop(); }, 0.1);
        $reactor->run();
    }

}
