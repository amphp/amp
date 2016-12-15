<?php declare(strict_types = 1);

namespace Amp\Test;

use Amp;
use Amp\{ Deferred, Emitter, Pause };

class EmitterTest extends \PHPUnit_Framework_TestCase {
    const TIMEOUT = 100;
    
    /**
     * @expectedException \Error
     * @expectedExceptionMessage The callable did not return a Generator
     */
    public function testNonGeneratorCallable() {
        $emitter = new Emitter(function () {});
    }
    
    public function testEmit() {
        $invoked = false;
        Amp\execute(function () use (&$invoked) {
            $value = 1;
    
            $emitter = new Emitter(function (callable $emit) use ($value) {
                yield $emit($value);
                return $value;
            });
    
            $invoked = false;
            $callback = function ($emitted) use (&$invoked, $value) {
                $invoked = true;
                $this->assertSame($emitted, $value);
            };
    
            $emitter->subscribe($callback);
            
            $emitter->when(function ($exception, $result) use ($value) {
                $this->assertSame($result, $value);
            });
        });
        
        $this->assertTrue($invoked);
    }
    
    /**
     * @depends testEmit
     */
    public function testEmitSuccessfulPromise() {
        $invoked = false;
        Amp\execute(function () use (&$invoked) {
            $deferred = new Deferred();
    
            $emitter = new Emitter(function (callable $emit) use ($deferred) {
                return yield $emit($deferred->promise());
            });
    
            $value = 1;
            $invoked = false;
            $callback = function ($emitted) use (&$invoked, $value) {
                $invoked = true;
                $this->assertSame($emitted, $value);
            };
    
            $emitter->subscribe($callback);
    
            $deferred->resolve($value);
        });
    
        $this->assertTrue($invoked);
    }
    
    /**
     * @depends testEmitSuccessfulPromise
     */
    public function testEmitFailedPromise() {
        $exception = new \Exception;
        Amp\execute(function () use ($exception) {
            $deferred = new Deferred();
            
            $emitter = new Emitter(function (callable $emit) use ($deferred) {
                return yield $emit($deferred->promise());
            });
            
            $deferred->fail($exception);
            
            $emitter->when(function ($reason) use ($exception) {
                $this->assertSame($reason, $exception);
            });
        });
    }
    
    /**
     * @depends testEmit
     */
    public function testEmitBackPressure() {
        $emits = 3;
        Amp\execute(function () use (&$time, $emits) {
            $emitter = new Emitter(function (callable $emit) use (&$time, $emits) {
                $time = microtime(true);
                for ($i = 0; $i < $emits; ++$i) {
                    yield $emit($i);
                }
                $time = microtime(true) - $time;
            });
    
            $emitter->subscribe(function () {
                return new Pause(self::TIMEOUT);
            });
        });
        
        $this->assertGreaterThan(self::TIMEOUT * $emits, $time * 1000);
    }
    
    /**
     * @depends testEmit
     */
    public function testSubscriberThrows() {
        $exception = new \Exception;
        
        try {
            Amp\execute(function () use ($exception) {
                $emitter = new Emitter(function (callable $emit) {
                    yield $emit(1);
                    yield $emit(2);
                });
        
                $emitter->subscribe(function () use ($exception) {
                    throw $exception;
                });
            });
        } catch (\Exception $caught) {
            $this->assertSame($exception, $caught);
        }
    }
    
    /**
     * @depends testEmit
     */
    public function testEmitterCoroutineThrows() {
        $exception = new \Exception;
    
        try {
            Amp\execute(function () use ($exception) {
                $emitter = new Emitter(function (callable $emit) use ($exception) {
                    yield $emit(1);
                    throw $exception;
                });
                
                Amp\wait($emitter);
            });
        } catch (\Exception $caught) {
            $this->assertSame($exception, $caught);
        }
    }
}
