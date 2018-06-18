<?php

namespace Amp\Test;

use Amp\Loop;
use Amp\Promise;

class Placeholder
{
    use \Amp\Internal\Placeholder {
        resolve as public;
        fail as public;
    }
}

class PlaceholderTraitTest extends \PHPUnit\Framework\TestCase
{
    /** @var \Amp\Test\Placeholder */
    private $placeholder;

    public function setUp()
    {
        $this->placeholder = new Placeholder;
    }

    public function testOnResolveOnSuccess()
    {
        $value = "Resolution value";

        $invoked = 0;
        $callback = function ($exception, $value) use (&$invoked, &$result) {
            ++$invoked;
            $result = $value;
        };

        $this->placeholder->onResolve($callback);

        $this->placeholder->resolve($value);

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
            ++$invoked;
            $result = $value;
        };

        $this->placeholder->onResolve($callback);
        $this->placeholder->onResolve($callback);
        $this->placeholder->onResolve($callback);

        $this->placeholder->resolve($value);

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
            ++$invoked;
            $result = $value;
        };

        $this->placeholder->resolve($value);

        $this->placeholder->onResolve($callback);

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
            ++$invoked;
            $result = $value;
        };

        $this->placeholder->resolve($value);

        $this->placeholder->onResolve($callback);
        $this->placeholder->onResolve($callback);
        $this->placeholder->onResolve($callback);

        $this->assertSame(3, $invoked);
        $this->assertSame($value, $result);
    }

    /**
     * @depends testOnResolveOnSuccess
     */
    public function testOnResolveThrowingForwardsToLoopHandlerOnSuccess()
    {
        Loop::run(function () use (&$invoked) {
            $invoked = 0;
            $expected = new \Exception;

            Loop::setErrorHandler(function ($exception) use (&$invoked, $expected) {
                ++$invoked;
                $this->assertSame($expected, $exception);
            });

            $callback = function () use ($expected) {
                throw $expected;
            };

            $this->placeholder->onResolve($callback);

            $this->placeholder->resolve($expected);
        });

        $this->assertSame(1, $invoked);
    }

    /**
     * @depends testOnResolveAfterSuccess
     */
    public function testOnResolveThrowingForwardsToLoopHandlerAfterSuccess()
    {
        Loop::run(function () use (&$invoked) {
            $invoked = 0;
            $expected = new \Exception;

            Loop::setErrorHandler(function ($exception) use (&$invoked, $expected) {
                ++$invoked;
                $this->assertSame($expected, $exception);
            });

            $callback = function () use ($expected) {
                throw $expected;
            };

            $this->placeholder->resolve($expected);

            $this->placeholder->onResolve($callback);
        });

        $this->assertSame(1, $invoked);
    }

    public function testOnResolveOnFail()
    {
        $exception = new \Exception;

        $invoked = 0;
        $callback = function ($exception, $value) use (&$invoked, &$result) {
            ++$invoked;
            $result = $exception;
        };

        $this->placeholder->onResolve($callback);

        $this->placeholder->fail($exception);

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
            ++$invoked;
            $result = $exception;
        };

        $this->placeholder->onResolve($callback);
        $this->placeholder->onResolve($callback);
        $this->placeholder->onResolve($callback);

        $this->placeholder->fail($exception);

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
            ++$invoked;
            $result = $exception;
        };

        $this->placeholder->fail($exception);

        $this->placeholder->onResolve($callback);

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
            ++$invoked;
            $result = $exception;
        };

        $this->placeholder->fail($exception);

        $this->placeholder->onResolve($callback);
        $this->placeholder->onResolve($callback);
        $this->placeholder->onResolve($callback);

        $this->assertSame(3, $invoked);
        $this->assertSame($exception, $result);
    }

    /**
     * @depends testOnResolveOnSuccess
     */
    public function testOnResolveThrowingForwardsToLoopHandlerOnFail()
    {
        Loop::run(function () use (&$invoked) {
            $invoked = 0;
            $expected = new \Exception;

            Loop::setErrorHandler(function ($exception) use (&$invoked, $expected) {
                ++$invoked;
                $this->assertSame($expected, $exception);
            });

            $callback = function () use ($expected) {
                throw $expected;
            };

            $this->placeholder->onResolve($callback);

            $this->placeholder->fail(new \Exception);
        });

        $this->assertSame(1, $invoked);
    }

    /**
     * @depends testOnResolveOnSuccess
     */
    public function testOnResolveThrowingForwardsToLoopHandlerAfterFail()
    {
        Loop::run(function () use (&$invoked) {
            $invoked = 0;
            $expected = new \Exception;

            Loop::setErrorHandler(function ($exception) use (&$invoked, $expected) {
                ++$invoked;
                $this->assertSame($expected, $exception);
            });

            $callback = function () use ($expected) {
                throw $expected;
            };

            $this->placeholder->fail(new \Exception);

            $this->placeholder->onResolve($callback);
        });

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
    }

    /**
     * @expectedException \Error
     * @expectedExceptionMessage Promise has already been resolved
     */
    public function testDoubleResolve()
    {
        $this->placeholder->resolve();
        $this->placeholder->resolve();
    }

    /**
     * @expectedException \Error
     * @expectedExceptionMessage Promise has already been resolved
     */
    public function testResolveAgainWithinOnResolveCallback()
    {
        Loop::run(function () {
            $this->placeholder->onResolve(function () {
                $this->placeholder->resolve();
            });

            $this->placeholder->resolve();
        });
    }

    public function testOnResolveWithGenerator()
    {
        $invoked = false;
        $this->placeholder->onResolve(function ($exception, $value) use (&$invoked) {
            $invoked = true;
            return $value;
            yield; // Unreachable, but makes function a generator.
        });

        $this->placeholder->resolve(1);

        $this->assertTrue($invoked);
    }

    /**
     * @depends testOnResolveWithGenerator
     */
    public function testOnResolveWithGeneratorAfterResolve()
    {
        $this->placeholder->resolve(1);

        $invoked = false;
        $this->placeholder->onResolve(function ($exception, $value) use (&$invoked) {
            $invoked = true;
            return $value;
            yield; // Unreachable, but makes function a generator.
        });

        $this->assertTrue($invoked);
    }
}
