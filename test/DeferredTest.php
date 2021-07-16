<?php

namespace Amp\Test;

use Amp\Deferred;
use Amp\Promise;

class DeferredTest extends BaseTest
{
    /** @var \Amp\Deferred */
    private $deferred;

    public function setUp(): void
    {
        $this->deferred = new Deferred;
    }

    public function testGetPromise()
    {
        $promise = $this->deferred->promise();
        $this->assertInstanceOf(Promise::class, $promise);
    }

    /**
     * @depends testGetPromise
     */
    public function testResolve()
    {
        $value = "Resolution value";
        $promise = $this->deferred->promise();

        $invoked = false;
        $promise->onResolve(function ($exception, $value) use (&$invoked, &$result) {
            $invoked = true;
            $result = $value;
        });

        $this->assertFalse($this->deferred->isResolved());

        $this->deferred->resolve($value);

        $this->assertTrue($this->deferred->isResolved());
        $this->assertTrue($invoked);
        $this->assertSame($value, $result);
    }

    /**
     * @depends testGetPromise
     */
    public function testFail()
    {
        $exception = new \Exception;
        $promise = $this->deferred->promise();

        $invoked = false;
        $promise->onResolve(function ($exception, $value) use (&$invoked, &$result) {
            $invoked = true;
            $result = $exception;
        });

        $this->assertFalse($this->deferred->isResolved());

        $this->deferred->fail($exception);

        $this->assertTrue($this->deferred->isResolved());
        $this->assertTrue($invoked);
        $this->assertSame($exception, $result);
    }
}
