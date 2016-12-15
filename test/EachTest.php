<?php declare(strict_types = 1);

namespace Amp\Test;

use Amp;
use Amp\{ Emitter, Observable, Postponed };
use Interop\Async\Loop;

class EachTest extends \PHPUnit_Framework_TestCase {
    public function testNoValuesEmitted() {
        $invoked = false;
        Loop::execute(function () use (&$invoked){
            $postponed = new Postponed;
            
            $observable = Amp\each($postponed->observe(), function ($value) use (&$invoked) {
                $invoked = true;
            });
            
            $this->assertInstanceOf(Observable::class, $observable);
            
            $postponed->resolve();
        });
        
        $this->assertFalse($invoked);
    }
    
    public function testValuesEmitted() {
        $count = 0;
        $values = [1, 2, 3];
        $final = 4;
        $results = [];
        Loop::execute(function () use (&$results, &$result, &$count, $values, $final) {
            $emitter = new Emitter(function (callable $emit) use ($values, $final) {
                foreach ($values as $value) {
                    yield $emit($value);
                }
                return $final;
            });
        
            $observable = Amp\each($emitter, function ($value) use (&$count) {
                ++$count;
                return $value + 1;
            }, function ($value) use (&$invoked) {
                return $value + 1;
            });
            
            $observable->subscribe(function ($value) use (&$results) {
                $results[] = $value;
            });
    
            $observable->when(function ($exception, $value) use (&$result) {
                $result = $value;
            });
        });
    
        $this->assertSame(\count($values), $count);
        $this->assertSame(\array_map(function ($value) { return $value + 1; }, $values), $results);
        $this->assertSame($final + 1, $result);
    }
    
    /**
     * @depends testValuesEmitted
     */
    public function testOnNextCallbackThrows() {
        $values = [1, 2, 3];
        $exception = new \Exception;
        Loop::execute(function () use (&$reason, $values, $exception) {
            $emitter = new Emitter(function (callable $emit) use ($values) {
                foreach ($values as $value) {
                    yield $emit($value);
                }
            });
        
            $observable = Amp\each($emitter, function () use ($exception) {
                throw $exception;
            });
        
            $observable->subscribe(function ($value) use (&$results) {
                $results[] = $value;
            });
    
            $callback = function ($exception, $value) use (&$reason) {
                $reason = $exception;
            };
    
            $observable->when($callback);
        });
        
        $this->assertSame($exception, $reason);
    }
    
    /**
     * @depends testValuesEmitted
     */
    public function testOnCompleteCallbackThrows() {
        $count = 0;
        $values = [1, 2, 3];
        $results = [];
        $exception = new \Exception;
        Loop::execute(function () use (&$reason, &$results, &$count, $values, $exception) {
            $emitter = new Emitter(function (callable $emit) use ($values) {
                foreach ($values as $value) {
                    yield $emit($value);
                }
            });
            
            $observable = Amp\each($emitter, function ($value) use (&$count) {
                ++$count;
                return $value + 1;
            }, function ($value) use ($exception) {
                throw $exception;
            });
            
            $observable->subscribe(function ($value) use (&$results) {
                $results[] = $value;
            });
            
            $callback = function ($exception, $value) use (&$reason) {
                $reason = $exception;
            };
            
            $observable->when($callback);
        });
    
        $this->assertSame(\count($values), $count);
        $this->assertSame(\array_map(function ($value) { return $value + 1; }, $values), $results);
        $this->assertSame($exception, $reason);
    }
    
    public function testObservableFails() {
        $invoked = false;
        $exception = new \Exception;
        Loop::execute(function () use (&$invoked, &$reason, &$exception){
            $postponed = new Postponed;
        
            $observable = Amp\each($postponed->observe(), function ($value) use (&$invoked) {
                $invoked = true;
            });
        
            $postponed->fail($exception);
    
            $callback = function ($exception, $value) use (&$reason) {
                $reason = $exception;
            };
    
            $observable->when($callback);
        });
    
        $this->assertFalse($invoked);
        $this->assertSame($exception, $reason);
    }
}
