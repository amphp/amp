<?php declare(strict_types = 1);

namespace Amp\Test;

use Amp;
use Amp\{ Emitter, Observer, Pause, Postponed };

class ObserverTest extends \PHPUnit_Framework_TestCase {
    const TIMEOUT = 10;
    
    public function testSingleEmittingObservable() {
        Amp\execute(function () {
            $value = 1;
            $observable = new Emitter(function (callable $emit) use ($value) {
                yield $emit($value);
                return $value;
            });
    
            $observer = new Observer($observable);
            
            while (yield $observer->advance()) {
                $this->assertSame($observer->getCurrent(), $value);
            }
            
            $this->assertSame($observer->getResult(), $value);
        });
    }
    
    /**
     * @depends testSingleEmittingObservable
     */
    public function testFastEmittingObservable() {
        Amp\execute(function () {
            $count = 10;
            
            $postponed = new Postponed;
            
            $observer = new Observer($postponed->observe());
    
            for ($i = 0; $i < $count; ++$i) {
                $promises[] = $postponed->emit($i);
            }
            
            $postponed->resolve($i);
    
            for ($i = 0; yield $observer->advance(); ++$i) {
                $this->assertSame($observer->getCurrent(), $i);
            }
            
            $this->assertSame($count, $i);
            $this->assertSame($observer->getResult(), $i);
        });
    }
    
    /**
     * @depends testSingleEmittingObservable
     */
    public function testSlowEmittingObservable() {
        Amp\execute(function () {
            $count = 10;
            $observable = new Emitter(function (callable $emit) use ($count) {
                for ($i = 0; $i < $count; ++$i) {
                    yield new Pause(self::TIMEOUT);
                    yield $emit($i);
                }
                return $i;
            });
            
            $observer = new Observer($observable);
            
            for ($i = 0; yield $observer->advance(); ++$i) {
                $this->assertSame($observer->getCurrent(), $i);
            }
    
            $this->assertSame($count, $i);
            $this->assertSame($observer->getResult(), $i);
        });
    }
    
    /**
     * @depends testFastEmittingObservable
     */
    public function testDrain() {
        Amp\execute(function () {
            $count = 10;
            
            $postponed = new Postponed;
            
            $observer = new Observer($postponed->observe());
            
            for ($i = 0; $i < $count; ++$i) {
                $promises[] = $postponed->emit($i);
            }
            
            $postponed->resolve($i);
            
            $values = $observer->drain();
            
            $this->assertSame(\range(0, $count - 1), $values);
        });
    }
    
    /**
     * @expectedException \Error
     * @expectedExceptionMessage The observable has not resolved
     */
    public function testDrainBeforeResolution() {
        $postponed = new Postponed;
    
        $observer = new Observer($postponed->observe());
    
        $observer->drain();
    }
    
    public function testFailingObservable() {
        Amp\execute(function () {
            $exception = new \Exception;
        
            $postponed = new Postponed;
        
            $observer = new Observer($postponed->observe());
            
            $postponed->fail($exception);
            
            try {
                while (yield $observer->advance());
                $this->fail("Observer::advance() should throw observable failure reason");
            } catch (\Exception $reason) {
                $this->assertSame($exception, $reason);
            }
            
            try {
                $result = $observer->getResult();
                $this->fail("Observer::getResult() should throw observable failure reason");
            } catch (\Exception $reason) {
                $this->assertSame($exception, $reason);
            }
        });
    }
    
    /**
     * @expectedException \Error
     * @expectedExceptionMessage Promise returned from advance() must resolve before calling this method
     */
    public function testGetCurrentBeforeAdvanceResolves() {
        $postponed = new Postponed;
        
        $observer = new Observer($postponed->observe());
        
        $promise = $observer->advance();
        
        $observer->getCurrent();
    }
    
    /**
     * @expectedException \Error
     * @expectedExceptionMessage The observable has resolved
     */
    public function testGetCurrentAfterResolution() {
        $postponed = new Postponed;
        
        $observer = new Observer($postponed->observe());
        
        $postponed->resolve();
        
        $observer->getCurrent();
    }
    
    /**
     * @expectedException \Error
     * @expectedExceptionMessage The observable has not resolved
     */
    public function testGetResultBeforeResolution() {
        Amp\execute(function () {
            $postponed = new Postponed;
            
            $observer = new Observer($postponed->observe());
            
            $observer->getResult();
        });
    }
}
