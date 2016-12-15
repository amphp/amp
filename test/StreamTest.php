<?php declare(strict_types = 1);

namespace Amp\Test;

use Amp;
use Amp\{ Failure, Pause, Success };
use Interop\Async\Loop;

class StreamTest extends \PHPUnit_Framework_TestCase {
    public function testSuccessfulPromises() {
        $results = [];
        Loop::execute(function () use (&$results) {
            $observable = Amp\stream([new Success(1), new Success(2), new Success(3)]);
    
            $observable->subscribe(function ($value) use (&$results) {
                $results[] = $value;
            });
        });
        
        $this->assertSame([1, 2, 3], $results);
    }
    
    public function testFailedPromises() {
        $exception = new \Exception;
        Loop::execute(function () use (&$reason, $exception) {
            $observable = Amp\stream([new Failure($exception), new Failure($exception)]);
            
            $callback = function ($exception, $value) use (&$reason) {
                $reason = $exception;
            };
    
            $observable->when($callback);
        });
        
        $this->assertSame($exception, $reason);
    }
    
    public function testMixedPromises() {
        $exception = new \Exception;
        $results = [];
        Loop::execute(function () use (&$results, &$reason, $exception) {
            $observable = Amp\stream([new Success(1), new Success(2), new Failure($exception), new Success(4)]);
    
            $observable->subscribe(function ($value) use (&$results) {
                $results[] = $value;
            });
            
            $callback = function ($exception, $value) use (&$reason) {
                $reason = $exception;
            };
            
            $observable->when($callback);
        });
        
        $this->assertSame([1, 2], $results);
        $this->assertSame($exception, $reason);
    }
    
    public function testPendingPromises() {
        
        $results = [];
        Loop::execute(function () use (&$results) {
            $observable = Amp\stream([new Pause(30, 1), new Pause(10, 2), new Pause(20, 3), new Success(4)]);
            
            $observable->subscribe(function ($value) use (&$results) {
                $results[] = $value;
            });
        });
        
        $this->assertSame([4, 2, 3, 1], $results);
    }
    
    /**
     * @expectedException \Error
     * @expectedExceptionMessage Non-promise provided
     */
    public function testNonPromise() {
        Amp\stream([1]);
    }
    
}
