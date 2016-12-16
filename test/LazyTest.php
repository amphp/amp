<?php declare(strict_types = 1);

namespace Amp\Test;

use Amp;
use Amp\{ Failure, Lazy, Success };
use Interop\Async\Loop;

class LazyTest extends \PHPUnit_Framework_TestCase {
    public function testPromisorNotCalledOnConstruct() {
        $invoked = false;
        $lazy = new Lazy(function () use (&$invoked) {
            $invoked = true;
        });
        $this->assertFalse($invoked);
    }
    
    public function testPromisorReturningScalar() {
        $invoked = false;
        $value = 1;
        $lazy = new Lazy(function () use (&$invoked, $value) {
            $invoked = true;
            return $value;
        });
        
        $lazy->when(function ($exception, $value) use (&$result) {
            $result = $value;
        });
        
        $this->assertTrue($invoked);
        $this->assertSame($value, $result);
    }
    
    public function testPromisorReturningSuccessfulPromise() {
        $invoked = false;
        $value = 1;
        $promise = new Success($value);
        $lazy = new Lazy(function () use (&$invoked, $promise) {
            $invoked = true;
            return $promise;
        });
        
        $lazy->when(function ($exception, $value) use (&$result) {
            $result = $value;
        });
        
        $this->assertTrue($invoked);
        $this->assertSame($value, $result);
    }
    
    public function testPromisorReturningFailedPromise() {
        $invoked = false;
        $exception = new \Exception;
        $promise = new Failure($exception);
        $lazy = new Lazy(function () use (&$invoked, $promise) {
            $invoked = true;
            return $promise;
        });
        
        $lazy->when(function ($exception, $value) use (&$reason) {
            $reason = $exception;
        });
        
        $this->assertTrue($invoked);
        $this->assertSame($exception, $reason);
    }
    
    public function testPromisorThrowingException() {
        $invoked = false;
        $exception = new \Exception;
        $lazy = new Lazy(function () use (&$invoked, $exception) {
            $invoked = true;
            throw $exception;
        });
        
        $lazy->when(function ($exception, $value) use (&$reason) {
            $reason = $exception;
        });
        
        $this->assertTrue($invoked);
        $this->assertSame($exception, $reason);
    }
}
