<?php declare(strict_types = 1);

namespace Amp\Test;

use Amp;
use Amp\Failure;
use Amp\Success;
use Interop\Async\Awaitable;

class PipeTest extends \PHPUnit_Framework_TestCase {
    public function testSuccessfulAwaitable() {
        $invoked = false;
        $callback = function ($value) use (&$invoked) {
            $invoked = true;
            return $value + 1;
        };

        $value = 1;

        $awaitable = new Success($value);

        $awaitable = Amp\pipe($awaitable, $callback);
        $this->assertInstanceOf(Awaitable::class, $awaitable);

        $callback = function ($exception, $value) use (&$result) {
            $result = $value;
        };

        $awaitable->when($callback);

        $this->assertTrue($invoked);
        $this->assertSame($value + 1, $result);
    }

    public function testFailedAwaitable() {
        $invoked = false;
        $callback = function ($value) use (&$invoked) {
            $invoked = true;
            return $value + 1;
        };

        $exception = new \Exception;

        $awaitable = new Failure($exception);

        $awaitable = Amp\pipe($awaitable, $callback);
        $this->assertInstanceOf(Awaitable::class, $awaitable);

        $callback = function ($exception, $value) use (&$reason) {
            $reason = $exception;
        };

        $awaitable->when($callback);

        $this->assertFalse($invoked);
        $this->assertSame($exception, $reason);
    }
    
    /**
     * @depends testSuccessfulAwaitable
     */
    public function testCallbackThrowing() {
        $exception = new \Exception;
        $callback = function ($value) use (&$invoked, $exception) {
            $invoked = true;
            throw $exception;
        };
    
        $value = 1;
    
        $awaitable = new Success($value);
    
        $awaitable = Amp\pipe($awaitable, $callback);
    
        $callback = function ($exception, $value) use (&$reason) {
            $reason = $exception;
        };
    
        $awaitable->when($callback);
    
        $this->assertTrue($invoked);
        $this->assertSame($exception, $reason);
    }
}
