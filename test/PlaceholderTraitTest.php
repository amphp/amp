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

class PlaceholderTraitTest extends BaseTest
{
    /** @var Placeholder */
    private $placeholder;

    public function setUp(): void
    {
        $this->placeholder = new Placeholder;
    }

    public function testOnResolveOnSuccess(): void
    {
        $value = "Resolution value";

        $invoked = 0;
        $callback = function ($exception, $value) use (&$invoked, &$result) {
            $result = $value;
            ++$invoked;
        };

        $this->placeholder->onResolve($callback);

        $this->placeholder->resolve($value);

        self::assertSame(1, $invoked);
        self::assertSame($value, $result);
    }

    /**
     * @depends testOnResolveOnSuccess
     */
    public function testMultipleOnResolvesOnSuccess(): void
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

        self::assertSame(3, $invoked);
        self::assertSame($value, $result);
    }

    /**
     * @depends testOnResolveOnSuccess
     */
    public function testOnResolveAfterSuccess(): void
    {
        $value = "Resolution value";

        $invoked = 0;
        $callback = function ($exception, $value) use (&$invoked, &$result) {
            $result = $value;
            ++$invoked;
        };

        $this->placeholder->resolve($value);

        $this->placeholder->onResolve($callback);

        self::assertSame(1, $invoked);
        self::assertSame($value, $result);
    }

    /**
     * @depends testOnResolveAfterSuccess
     */
    public function testMultipleOnResolveAfterSuccess(): void
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

        self::assertSame(3, $invoked);
        self::assertSame($value, $result);
    }

    /**
     * @depends testOnResolveOnSuccess
     */
    public function testOnResolveThrowingForwardsToLoopHandlerOnSuccess(): void
    {
        Loop::run(function () use (&$invoked) {
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
        });

        self::assertSame(1, $invoked);
    }

    /**
     * @depends testOnResolveAfterSuccess
     */
    public function testOnResolveThrowingForwardsToLoopHandlerAfterSuccess(): void
    {
        Loop::run(function () use (&$invoked) {
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
        });

        self::assertSame(1, $invoked);
    }

    public function testOnResolveOnFail(): void
    {
        $exception = new \Exception;

        $invoked = 0;
        $callback = function ($exception, $value) use (&$invoked, &$result) {
            $result = $exception;
            ++$invoked;
        };

        $this->placeholder->onResolve($callback);

        $this->placeholder->fail($exception);

        self::assertSame(1, $invoked);
        self::assertSame($exception, $result);
    }

    /**
     * @depends testOnResolveOnFail
     */
    public function testMultipleOnResolvesOnFail(): void
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

        self::assertSame(3, $invoked);
        self::assertSame($exception, $result);
    }

    /**
     * @depends testOnResolveOnFail
     */
    public function testOnResolveAfterFail(): void
    {
        $exception = new \Exception;

        $invoked = 0;
        $callback = function ($exception, $value) use (&$invoked, &$result) {
            $result = $exception;
            ++$invoked;
        };

        $this->placeholder->fail($exception);

        $this->placeholder->onResolve($callback);

        self::assertSame(1, $invoked);
        self::assertSame($exception, $result);
    }

    /**
     * @depends testOnResolveAfterFail
     */
    public function testMultipleOnResolvesAfterFail(): void
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

        self::assertSame(3, $invoked);
        self::assertSame($exception, $result);
    }

    /**
     * @depends testOnResolveOnSuccess
     */
    public function testOnResolveThrowingForwardsToLoopHandlerOnFail(): void
    {
        Loop::run(function () use (&$invoked) {
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
        });

        self::assertSame(1, $invoked);
    }

    /**
     * @depends testOnResolveOnSuccess
     */
    public function testOnResolveThrowingForwardsToLoopHandlerAfterFail(): void
    {
        Loop::run(function () use (&$invoked) {
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
        });

        self::assertSame(1, $invoked);
    }

    public function testResolveWithPromiseBeforeOnResolve(): void
    {
        $promise = $this->getMockBuilder(Promise::class)->getMock();

        $promise->expects(self::once())
            ->method("onResolve")
            ->with(self::callback("is_callable"));

        $this->placeholder->resolve($promise);

        $this->placeholder->onResolve(function () {
        });
    }

    public function testResolveWithPromiseAfterOnResolve(): void
    {
        $promise = $this->getMockBuilder(Promise::class)->getMock();

        $promise->expects(self::once())
            ->method("onResolve")
            ->with(self::callback("is_callable"));

        $this->placeholder->onResolve(function () {
        });

        $this->placeholder->resolve($promise);
    }

    public function testDoubleResolve(): void
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Promise has already been resolved');

        $this->placeholder->resolve();
        $this->placeholder->resolve();
    }

    public function testResolveAgainWithinOnResolveCallback(): void
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Promise has already been resolved');

        Loop::run(function () {
            $this->placeholder->onResolve(function () {
                $this->placeholder->resolve();
            });

            $this->placeholder->resolve();
        });
    }

    public function testOnResolveWithGenerator(): void
    {
        $invoked = false;
        $this->placeholder->onResolve(function ($exception, $value) use (&$invoked) {
            $invoked = true;
            return $value;
            yield; // Unreachable, but makes function a generator.
        });

        $this->placeholder->resolve(1);

        self::assertTrue($invoked);
    }

    /**
     * @depends testOnResolveWithGenerator
     */
    public function testOnResolveWithGeneratorAfterResolve(): void
    {
        $this->placeholder->resolve(1);

        $invoked = false;
        $this->placeholder->onResolve(function ($exception, $value) use (&$invoked) {
            $invoked = true;
            return $value;
            yield; // Unreachable, but makes function a generator.
        });

        self::assertTrue($invoked);
    }
}
