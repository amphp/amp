<?php

namespace Amp\Test;

use Amp\Future;
use Interop\Async\Awaitable;
use Interop\Async\Loop;

class FutureTest extends \PHPUnit_Framework_TestCase {
    /**
     * @var \Amp\Future
     */
    private $future;

    public function setUp() {
        $this->future = new Future;
    }

    public function testWhenOnSuccess() {
        $value = "Resolution value";

        $invoked = 0;
        $callback = function ($exception, $value) use (&$invoked, &$result) {
            ++$invoked;
            $result = $value;
        };

        $this->future->when($callback);

        $this->future->resolve($value);

        $this->assertSame(1, $invoked);
        $this->assertSame($value, $result);
    }

    /**
     * @depends testWhenOnSuccess
     */
    public function testMultipleWhensOnSuccess() {
        $value = "Resolution value";

        $invoked = 0;
        $callback = function ($exception, $value) use (&$invoked, &$result) {
            ++$invoked;
            $result = $value;
        };

        $this->future->when($callback);
        $this->future->when($callback);
        $this->future->when($callback);

        $this->future->resolve($value);

        $this->assertSame(3, $invoked);
        $this->assertSame($value, $result);
    }

    /**
     * @depends testWhenOnSuccess
     */
    public function testWhenAfterSuccess() {
        $value = "Resolution value";

        $invoked = 0;
        $callback = function ($exception, $value) use (&$invoked, &$result) {
            ++$invoked;
            $result = $value;
        };

        $this->future->resolve($value);

        $this->future->when($callback);

        $this->assertSame(1, $invoked);
        $this->assertSame($value, $result);
    }

    /**
     * @depends testWhenAfterSuccess
     */
    public function testMultipleWhenAfterSuccess() {
        $value = "Resolution value";

        $invoked = 0;
        $callback = function ($exception, $value) use (&$invoked, &$result) {
            ++$invoked;
            $result = $value;
        };

        $this->future->resolve($value);

        $this->future->when($callback);
        $this->future->when($callback);
        $this->future->when($callback);

        $this->assertSame(3, $invoked);
        $this->assertSame($value, $result);
    }

    /**
     * @depends testWhenOnSuccess
     */
    public function testWhenThrowingForwardsToLoopHandlerOnSuccess() {
        Loop::execute(function () use (&$invoked) {
            $invoked = 0;
            $expected = new \Exception;

            Loop::setErrorHandler(function ($exception) use (&$invoked, $expected) {
                ++$invoked;
                $this->assertSame($expected, $exception);
            });

            $callback = function () use ($expected) {
                throw $expected;
            };

            $this->future->when($callback);

            $this->future->resolve($expected);
        });

        $this->assertSame(1, $invoked);
    }

    /**
     * @depends testWhenAfterSuccess
     */
    public function testWhenThrowingForwardsToLoopHandlerAfterSuccess() {
        Loop::execute(function () use (&$invoked) {
            $invoked = 0;
            $expected = new \Exception;

            Loop::setErrorHandler(function ($exception) use (&$invoked, $expected) {
                ++$invoked;
                $this->assertSame($expected, $exception);
            });

            $callback = function () use ($expected) {
                throw $expected;
            };

            $this->future->resolve($expected);

            $this->future->when($callback);
        });

        $this->assertSame(1, $invoked);
    }

    public function testWhenOnFail() {
        $exception = new \Exception;

        $invoked = 0;
        $callback = function ($exception, $value) use (&$invoked, &$result) {
            ++$invoked;
            $result = $exception;
        };

        $this->future->when($callback);

        $this->future->fail($exception);

        $this->assertSame(1, $invoked);
        $this->assertSame($exception, $result);
    }

    /**
     * @depends testWhenOnFail
     */
    public function testMultipleWhensOnFail() {
        $exception = new \Exception;

        $invoked = 0;
        $callback = function ($exception, $value) use (&$invoked, &$result) {
            ++$invoked;
            $result = $exception;
        };

        $this->future->when($callback);
        $this->future->when($callback);
        $this->future->when($callback);

        $this->future->fail($exception);

        $this->assertSame(3, $invoked);
        $this->assertSame($exception, $result);
    }

    /**
     * @depends testWhenOnFail
     */
    public function testWhenAfterFail() {
        $exception = new \Exception;

        $invoked = 0;
        $callback = function ($exception, $value) use (&$invoked, &$result) {
            ++$invoked;
            $result = $exception;
        };

        $this->future->fail($exception);

        $this->future->when($callback);

        $this->assertSame(1, $invoked);
        $this->assertSame($exception, $result);
    }

    /**
     * @depends testWhenAfterFail
     */
    public function testMultipleWhensAfterFail() {
        $exception = new \Exception;

        $invoked = 0;
        $callback = function ($exception, $value) use (&$invoked, &$result) {
            ++$invoked;
            $result = $exception;
        };

        $this->future->fail($exception);

        $this->future->when($callback);
        $this->future->when($callback);
        $this->future->when($callback);

        $this->assertSame(3, $invoked);
        $this->assertSame($exception, $result);
    }

    /**
     * @depends testWhenOnSuccess
     */
    public function testWhenThrowingForwardsToLoopHandlerOnFail() {
        Loop::execute(function () use (&$invoked) {
            $invoked = 0;
            $expected = new \Exception;

            Loop::setErrorHandler(function ($exception) use (&$invoked, $expected) {
                ++$invoked;
                $this->assertSame($expected, $exception);
            });

            $callback = function () use ($expected) {
                throw $expected;
            };

            $this->future->when($callback);

            $this->future->fail(new \Exception);
        });

        $this->assertSame(1, $invoked);
    }

    /**
     * @depends testWhenOnSuccess
     */
    public function testWhenThrowingForwardsToLoopHandlerAfterFail() {
        Loop::execute(function () use (&$invoked) {
            $invoked = 0;
            $expected = new \Exception;

            Loop::setErrorHandler(function ($exception) use (&$invoked, $expected) {
                ++$invoked;
                $this->assertSame($expected, $exception);
            });

            $callback = function () use ($expected) {
                throw $expected;
            };

            $this->future->fail(new \Exception);

            $this->future->when($callback);
        });

        $this->assertSame(1, $invoked);
    }

    public function testResolveWithAwaitableBeforeWhen() {
        $awaitable = $this->getMockBuilder(Awaitable::class)->getMock();

        $awaitable->expects($this->once())
            ->method("when")
            ->with($this->callback("is_callable"));

        $this->future->resolve($awaitable);

        $this->future->when(function () {});
    }

    public function testResolveWithAwaitableAfterWhen() {
        $awaitable = $this->getMockBuilder(Awaitable::class)->getMock();

        $awaitable->expects($this->once())
            ->method("when")
            ->with($this->callback("is_callable"));

        $this->future->when(function () {});

        $this->future->resolve($awaitable);
    }

    /**
     * @expectedException \LogicException
     */
    public function testDoubleResolve() {
        $this->future->resolve();
        $this->future->resolve();
    }
}
