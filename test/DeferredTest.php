<?php

namespace Amp\Test;

use Amp\Deferred;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Promise;
use function Amp\delay;

class DeferredTest extends AsyncTestCase
{
    private Deferred $deferred;

    public function setUp(): void
    {
        parent::setUp();

        $this->deferred = new Deferred;
    }

    public function testGetPromise(): void
    {
        $promise = $this->deferred->promise();
        $this->assertInstanceOf(Promise::class, $promise);
    }

    /**
     * @depends testGetPromise
     */
    public function testResolve(): void
    {
        $value = "Resolution value";
        $promise = $this->deferred->promise();

        $this->assertFalse($this->deferred->isResolved());

        $invoked = false;
        $promise->onResolve(function ($exception, $value) use (&$invoked, &$result) {
            $invoked = true;
            $result = $value;
        });

        $this->deferred->resolve($value);

        $this->assertFalse($invoked); // Resolution should be async.

        delay(0); // Force loop to tick once.

        $this->assertTrue($this->deferred->isResolved());
        $this->assertTrue($invoked);
        $this->assertSame($value, $result);
    }

    /**
     * @depends testGetPromise
     */
    public function testFail(): void
    {
        $exception = new \Exception;
        $promise = $this->deferred->promise();

        $invoked = false;
        $promise->onResolve(function ($exception, $value) use (&$invoked, &$result) {
            $invoked = true;
            $result = $exception;
        });

        $this->deferred->fail($exception);

        $this->assertFalse($invoked); // Resolution should be async.

        delay(0); // Force loop to tick once.

        $this->assertTrue($invoked);
        $this->assertSame($exception, $result);
    }
}
