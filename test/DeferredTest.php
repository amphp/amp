<?php declare(strict_types = 1);

namespace Amp\Test;

use Amp\Deferred;
use Interop\Async\Awaitable;

class DeferredTest extends \PHPUnit_Framework_TestCase {
    /** @var \Amp\Deferred */
    private $deferred;

    public function setUp() {
        $this->deferred = new Deferred;
    }

    public function testGetAwaitable() {
        $awaitable = $this->deferred->getAwaitable();
        $this->assertInstanceOf(Awaitable::class, $awaitable);
    }
    
    /**
     * @depends testGetAwaitable
     */
    public function testResolve() {
        $value = "Resolution value";
        $awaitable = $this->deferred->getAwaitable();

        $invoked = false;
        $awaitable->when(function ($exception, $value) use (&$invoked, &$result) {
            $invoked = true;
            $result = $value;
        });
        
        $this->deferred->resolve($value);

        $this->assertTrue($invoked);
        $this->assertSame($value, $result);
    }

    /**
     * @depends testGetAwaitable
     */
    public function testFail() {
        $exception = new \Exception;
        $awaitable = $this->deferred->getAwaitable();

        $invoked = false;
        $awaitable->when(function ($exception, $value) use (&$invoked, &$result) {
            $invoked = true;
            $result = $exception;
        });

        $this->deferred->fail($exception);

        $this->assertTrue($invoked);
        $this->assertSame($exception, $result);
    }
}
