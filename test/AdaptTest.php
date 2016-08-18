<?php declare(strict_types = 1);

namespace Amp\Test;

use Amp;
use Amp\Failure;
use Amp\Success;
use Interop\Async\Awaitable;

class PromiseMock {
    /** @var \Interop\Async\Awaitable */
    private $awaitable;

    public function __construct(Awaitable $awaitable) {
        $this->awaitable = $awaitable;
    }

    public function then(callable $onFulfilled = null, callable $onRejected = null) {
        $this->awaitable->when(function ($exception, $value) use ($onFulfilled, $onRejected) {
            if ($exception) {
                if ($onRejected) {
                    $onRejected($exception);
                }
                return;
            }

            if ($onFulfilled) {
                $onFulfilled($value);
            }
        });
    }
}

class AdaptTest extends \PHPUnit_Framework_TestCase {
    public function testThenCalled() {
        $mock = $this->getMockBuilder(PromiseMock::class)
            ->disableOriginalConstructor()
            ->getMock();
        
        $mock->expects($this->once())
            ->method("then")
            ->with(
                $this->callback(function ($resolve) {
                    return is_callable($resolve);
                }),
                $this->callback(function ($reject) {
                    return is_callable($reject);
                })
            );
        
        $awaitable = Amp\adapt($mock);
        
        $this->assertInstanceOf(Awaitable::class, $awaitable);
    }
    
    /**
     * @depends testThenCalled
     */
    public function testAwaitableFulfilled() {
        $value = 1;

        $promise = new PromiseMock(new Success($value));

        $awaitable = Amp\adapt($promise);

        $awaitable->when(function ($exception, $value) use (&$result) {
            $result = $value;
        });

        $this->assertSame($value, $result);
    }
    
    /**
     * @depends testThenCalled
     */
    public function testAwaitableRejected() {
        $exception = new \Exception;

        $promise = new PromiseMock(new Failure($exception));

        $awaitable = Amp\adapt($promise);

        $awaitable->when(function ($exception, $value) use (&$reason) {
            $reason = $exception;
        });

        $this->assertSame($exception, $reason);
    }

    /**
     * @expectedException \Error
     */
    public function testScalarValue() {
        Amp\adapt(1);
    }

    /**
     * @expectedException \Error
     */
    public function testNonThenableObject() {
        Amp\adapt(new \stdClass);
    }
}
