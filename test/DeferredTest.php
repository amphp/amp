<?php

namespace Amp\Test;

use Amp\Deferred;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Promise;
use function Revolt\EventLoop\delay;

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
        self::assertInstanceOf(Promise::class, $promise);
    }

    /**
     * @depends testGetPromise
     */
    public function testResolve(): void
    {
        $value = "Resolution value";
        $promise = $this->deferred->promise();

        self::assertFalse($this->deferred->isResolved());

        $invoked = false;
        $promise->onResolve(function ($exception, $value) use (&$invoked, &$result) {
            $invoked = true;
            $result = $value;
        });

        $this->deferred->resolve($value);

        self::assertFalse($invoked); // Resolution should be async.

        delay(0); // Force loop to tick once.

        self::assertTrue($this->deferred->isResolved());
        self::assertTrue($invoked);
        self::assertSame($value, $result);
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

        self::assertFalse($invoked); // Resolution should be async.

        delay(0); // Force loop to tick once.

        self::assertTrue($invoked);
        self::assertSame($exception, $result);
    }
}
