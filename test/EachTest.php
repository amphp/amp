<?php

namespace Amp\Test;

use Amp;
use Amp\{ Producer, Stream, Emitter };
use Interop\Async\Loop;

class EachTest extends \PHPUnit_Framework_TestCase {
    public function testNoValuesEmitted() {
        $invoked = false;
        Loop::execute(function () use (&$invoked){
            $emitter = new Emitter;
            
            $stream = Amp\each($emitter->stream(), function ($value) use (&$invoked) {
                $invoked = true;
            });
            
            $this->assertInstanceOf(Stream::class, $stream);
            
            $emitter->resolve();
        });
        
        $this->assertFalse($invoked);
    }
    
    public function testValuesEmitted() {
        $count = 0;
        $values = [1, 2, 3];
        $final = 4;
        $results = [];
        Loop::execute(function () use (&$results, &$result, &$count, $values, $final) {
            $producer = new Producer(function (callable $emit) use ($values, $final) {
                foreach ($values as $value) {
                    yield $emit($value);
                }
                return $final;
            });
        
            $stream = Amp\each($producer, function ($value) use (&$count) {
                ++$count;
                return $value + 1;
            }, function ($value) use (&$invoked) {
                return $value + 1;
            });
            
            $stream->listen(function ($value) use (&$results) {
                $results[] = $value;
            });
    
            $stream->when(function ($exception, $value) use (&$result) {
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
            $producer = new Producer(function (callable $emit) use ($values) {
                foreach ($values as $value) {
                    yield $emit($value);
                }
            });
        
            $stream = Amp\each($producer, function () use ($exception) {
                throw $exception;
            });
        
            $stream->listen(function ($value) use (&$results) {
                $results[] = $value;
            });
    
            $callback = function ($exception, $value) use (&$reason) {
                $reason = $exception;
            };
    
            $stream->when($callback);
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
            $producer = new Producer(function (callable $emit) use ($values) {
                foreach ($values as $value) {
                    yield $emit($value);
                }
            });
            
            $stream = Amp\each($producer, function ($value) use (&$count) {
                ++$count;
                return $value + 1;
            }, function ($value) use ($exception) {
                throw $exception;
            });
            
            $stream->listen(function ($value) use (&$results) {
                $results[] = $value;
            });
            
            $callback = function ($exception, $value) use (&$reason) {
                $reason = $exception;
            };
            
            $stream->when($callback);
        });
    
        $this->assertSame(\count($values), $count);
        $this->assertSame(\array_map(function ($value) { return $value + 1; }, $values), $results);
        $this->assertSame($exception, $reason);
    }
    
    public function testStreamFails() {
        $invoked = false;
        $exception = new \Exception;
        Loop::execute(function () use (&$invoked, &$reason, &$exception){
            $emitter = new Emitter;
        
            $stream = Amp\each($emitter->stream(), function ($value) use (&$invoked) {
                $invoked = true;
            });
        
            $emitter->fail($exception);
    
            $callback = function ($exception, $value) use (&$reason) {
                $reason = $exception;
            };
    
            $stream->when($callback);
        });
    
        $this->assertFalse($invoked);
        $this->assertSame($exception, $reason);
    }
}
