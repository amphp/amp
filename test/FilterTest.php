<?php declare(strict_types = 1);

namespace Amp\Test;

use Amp;
use Amp\{ Emitter, Observable, Postponed };
use Interop\Async\Loop;

class FilterTest extends \PHPUnit_Framework_TestCase {
    public function testNoValuesEmitted() {
        $invoked = false;
        Loop::execute(function () use (&$invoked){
            $postponed = new Postponed;
            
            $observable = Amp\filter($postponed->observe(), function ($value) use (&$invoked) {
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
        $results = [];
        $expected = [1, 3];
        Loop::execute(function () use (&$results, &$result, &$count, $values) {
            $emitter = new Emitter(function (callable $emit) use ($values) {
                foreach ($values as $value) {
                    yield $emit($value);
                }
            });
        
            $observable = Amp\filter($emitter, function ($value) use (&$count) {
                ++$count;
                return $value & 1;
            });
            
            $observable->subscribe(function ($value) use (&$results) {
                $results[] = $value;
            });
    
            $observable->when(function ($exception, $value) use (&$result) {
                $result = $value;
            });
        });
    
        $this->assertSame(\count($values), $count);
        $this->assertSame($expected, $results);
    }
    
    /**
     * @depends testValuesEmitted
     */
    public function testCallbackThrows() {
        $values = [1, 2, 3];
        $exception = new \Exception;
        Loop::execute(function () use (&$reason, $values, $exception) {
            $emitter = new Emitter(function (callable $emit) use ($values) {
                foreach ($values as $value) {
                    yield $emit($value);
                }
            });
            
            $observable = Amp\filter($emitter, function () use ($exception) {
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
    
    public function testObservableFails() {
        $invoked = false;
        $exception = new \Exception;
        Loop::execute(function () use (&$invoked, &$reason, &$exception){
            $postponed = new Postponed;
            
            $observable = Amp\filter($postponed->observe(), function ($value) use (&$invoked) {
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
