<?php

namespace Amp\Test;

use Amp\Internal\Placeholder;
use Amp\Loop;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Promise;
use function Amp\delay;

class InternalPlaceholderTest extends AsyncTestCase
{
    private Placeholder $placeholder;

    public function setUp(): void
    {
        parent::setUp();

        $this->placeholder = new Placeholder;
    }

    public function testOnResolveOnSuccess()
    {
        $value = "Resolution value";

        $invoked = 0;
        $callback = function ($exception, $value) use (&$invoked, &$result) {
            $result = $value;
            ++$invoked;
        };

        $this->placeholder->onResolve($callback);

        $this->placeholder->resolve($value);

        delay(0); // Tick event loop to invoke callbacks.

        $this->assertSame(1, $invoked);
        $this->assertSame($value, $result);
    }

    /**
     * @depends testOnResolveOnSuccess
     */
    public function testMultipleOnResolvesOnSuccess()
    {
        $value = "Resolution value";

        $invoked = 0;
        $callback = function ($exception, $value) use (&$invoked, &$result) {
            $result = $value;
            ++$invoked;
        };

        $this->placeholder->onResolve($callback);
        $this->placeholder->onResolve($callback);
        $this->placeholder->onResolve($callback);

        $this->placeholder->resolve($value);

        delay(0); // Tick event loop to invoke callbacks.

        $this->assertSame(3, $invoked);
        $this->assertSame($value, $result);
    }

    /**
     * @depends testOnResolveOnSuccess
     */
    public function testOnResolveAfterSuccess()
    {
        $value = "Resolution value";

        $invoked = 0;
        $callback = function ($exception, $value) use (&$invoked, &$result) {
            $result = $value;
            ++$invoked;
        };

        $this->placeholder->resolve($value);

        $this->placeholder->onResolve($callback);

        delay(0); // Tick event loop to invoke callbacks.

        $this->assertSame(1, $invoked);
        $this->assertSame($value, $result);
    }

    /**
     * @depends testOnResolveAfterSuccess
     */
    public function testMultipleOnResolveAfterSuccess()
    {
        $value = "Resolution value";

        $invoked = 0;
        $callback = function ($exception, $value) use (&$invoked, &$result) {
            $result = $value;
            ++$invoked;
        };

        $this->placeholder->resolve($value);

        $this->placeholder->onResolve($callback);
        $this->placeholder->onResolve($callback);
        $this->placeholder->onResolve($callback);

        delay(0); // Tick event loop to invoke callbacks.

        $this->assertSame(3, $invoked);
        $this->assertSame($value, $result);
    }

    /**
     * @depends testOnResolveOnSuccess
     */
    public function testOnResolveThrowingForwardsToLoopHandlerOnSuccess()
    {
        $invoked = 0;
        $expected = new \Exception;

        Loop::setErrorHandler(function ($exception) use (&$invoked, $expected) {
            $this->assertSame($expected, $exception);
            ++$invoked;
        });

        $callback = function () use ($expected) {
            throw $expected;
        };

        $this->placeholder->onResolve($callback);

        $this->placeholder->resolve($expected);

        delay(0); // Tick event loop to invoke callbacks.
    }

    /**
     * @depends testOnResolveAfterSuccess
     */
    public function testOnResolveThrowingForwardsToLoopHandlerAfterSuccess(): void
    {
        $invoked = 0;
        $expected = new \Exception;

        Loop::setErrorHandler(function ($exception) use (&$invoked, $expected) {
            $this->assertSame($expected, $exception);
            ++$invoked;
        });

        $callback = function () use ($expected) {
            throw $expected;
        };

        $this->placeholder->resolve($expected);

        $this->placeholder->onResolve($callback);

        delay(0); // Tick event loop to invoke callbacks.

        $this->assertSame(1, $invoked);
    }

    public function testOnResolveOnFail()
    {
        $exception = new \Exception;

        $invoked = 0;
        $callback = function ($exception, $value) use (&$invoked, &$result) {
            $result = $exception;
            ++$invoked;
        };

        $this->placeholder->onResolve($callback);

        $this->placeholder->fail($exception);

        delay(0); // Tick event loop to invoke callbacks.

        $this->assertSame(1, $invoked);
        $this->assertSame($exception, $result);
    }

    /**
     * @depends testOnResolveOnFail
     */
    public function testMultipleOnResolvesOnFail()
    {
        $exception = new \Exception;

        $invoked = 0;
        $callback = function ($exception, $value) use (&$invoked, &$result) {
            $result = $exception;
            ++$invoked;
        };

        $this->placeholder->onResolve($callback);
        $this->placeholder->onResolve($callback);
        $this->placeholder->onResolve($callback);

        $this->placeholder->fail($exception);

        delay(0); // Tick event loop to invoke callbacks.

        $this->assertSame(3, $invoked);
        $this->assertSame($exception, $result);
    }

    /**
     * @depends testOnResolveOnFail
     */
    public function testOnResolveAfterFail()
    {
        $exception = new \Exception;

        $invoked = 0;
        $callback = function ($exception, $value) use (&$invoked, &$result) {
            $result = $exception;
            ++$invoked;
        };

        $this->placeholder->fail($exception);

        $this->placeholder->onResolve($callback);

        delay(0); // Tick event loop to invoke callbacks.

        $this->assertSame(1, $invoked);
        $this->assertSame($exception, $result);
    }

    /**
     * @depends testOnResolveAfterFail
     */
    public function testMultipleOnResolvesAfterFail()
    {
        $exception = new \Exception;

        $invoked = 0;
        $callback = function ($exception, $value) use (&$invoked, &$result) {
            $result = $exception;
            ++$invoked;
        };

        $this->placeholder->fail($exception);

        $this->placeholder->onResolve($callback);
        $this->placeholder->onResolve($callback);
        $this->placeholder->onResolve($callback);

        delay(0); // Tick event loop to invoke callbacks.

        $this->assertSame(3, $invoked);
        $this->assertSame($exception, $result);
    }

    /**
     * @depends testOnResolveOnSuccess
     */
    public function testOnResolveThrowingForwardsToLoopHandlerOnFail()
    {
        $invoked = 0;
        $expected = new \Exception;

        Loop::setErrorHandler(function ($exception) use (&$invoked, $expected) {
            $this->assertSame($expected, $exception);
            ++$invoked;
        });

        $callback = function () use ($expected) {
            throw $expected;
        };

        $this->placeholder->onResolve($callback);

        $this->placeholder->fail(new \Exception);

        delay(0); // Tick event loop to invoke callbacks.

        $this->assertSame(1, $invoked);
    }

    /**
     * @depends testOnResolveOnSuccess
     */
    public function testOnResolveThrowingForwardsToLoopHandlerAfterFail()
    {
        $invoked = 0;
        $expected = new \Exception;

        Loop::setErrorHandler(function ($exception) use (&$invoked, $expected) {
            $this->assertSame($expected, $exception);
            ++$invoked;
        });

        $callback = function () use ($expected) {
            throw $expected;
        };

        $this->placeholder->fail(new \Exception);

        $this->placeholder->onResolve($callback);

        delay(0); // Tick event loop to invoke callbacks.

        $this->assertSame(1, $invoked);
    }

    public function testResolveWithPromiseBeforeOnResolve()
    {
        $promise = $this->getMockBuilder(Promise::class)->getMock();

        $promise->expects($this->once())
            ->method("onResolve")
            ->with($this->callback("is_callable"));

        $this->placeholder->resolve($promise);

        $this->placeholder->onResolve(function () {
        });

        delay(0); // Tick event loop to invoke callbacks.
    }

    public function testResolveWithPromiseAfterOnResolve()
    {
        $promise = $this->getMockBuilder(Promise::class)->getMock();

        $promise->expects($this->once())
            ->method("onResolve")
            ->with($this->callback("is_callable"));

        $this->placeholder->onResolve(function () {
        });

        $this->placeholder->resolve($promise);

        delay(0); // Tick event loop to invoke callbacks.
    }

    public function testDoubleResolve()
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage("Promise has already been resolved");

        $this->placeholder->resolve();
        $this->placeholder->resolve();
    }

    public function testResolveAgainWithinOnResolveCallback()
    {
        $this->placeholder->onResolve(function () {
            $this->placeholder->resolve();
        });

        $this->placeholder->resolve();

        Loop::setErrorHandler(function (\Throwable $exception) use (&$reason): void {
            $reason = $exception;
        });

        delay(0); // Tick event loop to invoke error callback.

        $this->assertInstanceOf(\Error::class, $reason);
        $this->assertStringContainsString("Promise has already been resolved", $reason->getMessage());
    }
}
