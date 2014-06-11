<?php

namespace Alert;

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

        $reactor->once(function() use (&$increment, $reactor) {
            $increment++;
            $reactor->stop();
        }, $msDelay = 100);

        $reactor->immediately([$reactor, 'stop']);
        $reactor->run();
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

        $firstWatcherId = 0;
        $watcherId = $reactor->once(function(){}, $delay = 0);
        $this->assertSame($firstWatcherId, $watcherId);

        $watcherId = $reactor->immediately(function(){});
        $this->assertSame($firstWatcherId + 1, $watcherId);
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

        $firstWatcherId = 0;
        $watcherId = $reactor->repeat(function(){}, $msInterval = 1000);
        $this->assertSame($firstWatcherId, $watcherId);

        $watcherId = $reactor->repeat(function(){}, $msInterval = 1000);
        $this->assertSame($firstWatcherId + 1, $watcherId);
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

        $reactor->once(function() use ($reactor, $watcherId) {
            $reactor->enable($watcherId);
        }, $msDelay = 10);

        $reactor->once([$reactor, 'stop'], $msDelay = 100);
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
}
