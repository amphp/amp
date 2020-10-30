<?php

namespace Amp\Test;

use Amp\Coroutine;
use Amp\Delayed;
use Amp\Failure;
use Amp\Loop;
use Amp\PHPUnit\AsyncTestCase;
use Amp\PHPUnit\TestException;
use Amp\Promise;
use Amp\Success;
use function Amp\await;
use function Amp\call;
use function Amp\delay;

class CoroutineTest extends AsyncTestCase
{
    const TIMEOUT = 100;

    public function testYieldSuccessfulPromise(): void
    {
        $value = 1;

        $generator = function () use (&$yielded, $value) {
            $yielded = yield new Success($value);
        };

        await(new Coroutine($generator()));

        $this->assertSame($value, $yielded);
    }

    public function testYieldFailedPromise(): void
    {
        $exception = new \Exception;

        $generator = function () use (&$yielded, $exception) {
            $yielded = yield new Failure($exception);
        };

        $coroutine = new Coroutine($generator());

        delay(0); // Force loop to tick once.

        $this->assertNull($yielded);

        $coroutine->onResolve(function ($exception) use (&$reason) {
            $reason = $exception;
        });

        delay(0); // Force loop to tick once.

        $this->assertSame($exception, $reason);
    }

    /**
     * @depends testYieldSuccessfulPromise
     */
    public function testYieldPendingPromise(): void
    {
        $value = 1;

        $generator = function () use (&$yielded, $value) {
            $yielded = yield new Delayed(self::TIMEOUT, $value);
        };

        await(new Coroutine($generator()));

        $this->assertSame($value, $yielded);
    }

    public function testYieldPromiseArray(): void
    {
        $value = 1;

        $generator = function () use (&$yielded, $value) {
            list($yielded) = yield [
                new Success($value),
            ];
        };

        await(new Coroutine($generator()));

        $this->assertSame($value, $yielded);
    }

    public function testYieldNonPromiseArray()
    {
        $this->expectException(\TypeError::class);

        $value = 1;

        $generator = function () use (&$yielded, $value) {
            list($yielded) = yield [
                $value,
            ];
        };

        await(new Coroutine($generator()));
    }

    public function testYieldPromiseArrayAfterPendingPromise()
    {
        $value = 1;

        $generator = function () use (&$yielded, $value) {
            yield new Delayed(10);
            list($yielded) = yield [
                new Success($value),
            ];
        };

        await(new Coroutine($generator()));

        $this->assertSame($value, $yielded);
    }

    public function testYieldNonPromiseArrayAfterPendingPromise()
    {
        $this->expectException(\TypeError::class);

        $value = 1;

        $generator = function () use (&$yielded, $value) {
            yield new Delayed(10);
            list($yielded) = yield [
                $value,
            ];
        };

        await(new Coroutine($generator()));
    }

    /**
     * @depends testYieldFailedPromise
     */
    public function testCatchingFailedPromiseException()
    {
        $exception = new \Exception;

        $fail = false;
        $generator = function () use (&$fail, &$result, $exception) {
            try {
                yield new Failure($exception);
            } catch (\Exception $exception) {
                $result = $exception;
                return;
            }

            $fail = true;
        };

        await(new Coroutine($generator()));

        $this->assertFalse($fail);
    }

    public function testInvalidYield()
    {
        $this->expectException(\TypeError::class);

        $generator = function () {
            yield 1;
        };

        await(new Coroutine($generator()));
    }

    /**
     * @depends testInvalidYield
     */
    public function testInvalidYieldAfterYieldPromise()
    {
        $this->expectException(\TypeError::class);

        $generator = function () {
            yield new Success;
            yield 1;
        };

        await(new Coroutine($generator()));
    }

    /**
     * @depends testInvalidYield
     */
    public function testInvalidYieldCatchingThrownError()
    {
        $value = 42;
        $generator = function () use ($value) {
            try {
                yield 1;
            } catch (\Error $error) {
                // No further yields.
            }

            return $value;
        };

        $result = await(new Coroutine($generator()));

        $this->assertSame($result, $value);
    }

    /**
     * @depends testInvalidYieldCatchingThrownError
     */
    public function testInvalidYieldCatchingThrownErrorAndYieldingAgain()
    {
        $value = 42;
        $generator = function () use ($value) {
            try {
                yield 1;
            } catch (\Error $error) {
                return yield new Success($value);
            }
        };

        $result = await(new Coroutine($generator()));

        $this->assertSame($result, $value);
    }

    /**
     * @depends testInvalidYieldCatchingThrownError
     */
    public function testInvalidYieldCatchingThrownErrorAndThrowing()
    {
        $exception = new \Exception;
        $generator = function () use ($exception) {
            try {
                yield 1;
            } catch (\Error $error) {
                throw $exception;
            }
        };

        try {
            await(new Coroutine($generator()));
        } catch (\Throwable $reason) {
            $this->assertSame($exception, $reason);
            return;
        }

        $this->fail("Coroutine should have failed");
    }

    /**
     * @depends testInvalidYieldCatchingThrownError
     */
    public function testInvalidYieldWithThrowingFinallyBlock()
    {
        $exception = new \Exception;
        $generator = function () use ($exception) {
            try {
                yield 1;
            } finally {
                throw $exception;
            }
        };

        try {
            await(new Coroutine($generator()));
        } catch (\Throwable $reason) {
            $this->assertSame($exception, $reason);
            $this->assertInstanceOf(\TypeError::class, $reason->getPrevious());
            return;
        }

        $this->fail("Coroutine should have failed");
    }

    /**
     * @depends testYieldFailedPromise
     */
    public function testCatchingFailedPromiseExceptionWithNoFurtherYields()
    {
        $exception = new \Exception;

        $generator = function () use ($exception) {
            try {
                yield new Failure($exception);
            } catch (\Exception $exception) {
                // No further yields in generator.
            }
        };

        $result = await(new Coroutine($generator()));

        $this->assertNull($result);
    }

    public function testGeneratorThrowingExceptionFailsCoroutine()
    {
        $exception = new \Exception;

        $generator = function () use ($exception) {
            throw $exception;
            yield;
        };

        try {
            await(new Coroutine($generator()));
        } catch (\Throwable $reason) {
            $this->assertSame($exception, $reason);
            return;
        }
    }

    /**
     * @depends testGeneratorThrowingExceptionFailsCoroutine
     */
    public function testGeneratorThrowingExceptionWithFinallyFailsCoroutine()
    {
        $exception = new \Exception;

        $invoked = false;
        $generator = function () use (&$invoked, $exception) {
            try {
                throw $exception;
                yield;
            } finally {
                $invoked = true;
            }
        };

        try {
            await(new Coroutine($generator()));
        } catch (\Throwable $reason) {
            $this->assertSame($exception, $reason);
            $this->assertTrue($invoked);
            return;
        }

        $this->fail("Coroutine should have failed");
    }

    /**
     * @depends testYieldFailedPromise
     * @depends testGeneratorThrowingExceptionWithFinallyFailsCoroutine
     */
    public function testGeneratorYieldingFailedPromiseWithFinallyFailsCoroutine()
    {
        $exception = new \Exception;

        $invoked = false;
        $generator = function () use (&$invoked, $exception) {
            try {
                yield new Failure($exception);
            } finally {
                $invoked = true;
            }
        };

        try {
            await(new Coroutine($generator()));
        } catch (\Throwable $reason) {
            $this->assertSame($exception, $reason);
            $this->assertTrue($invoked);
            return;
        }

        $this->fail("Coroutine should have failed");
    }

    /**
     * @depends testGeneratorThrowingExceptionFailsCoroutine
     */
    public function testGeneratorThrowingExceptionAfterPendingPromiseWithFinallyFailsCoroutine()
    {
        $exception = new \Exception;
        $value = 1;

        $invoked = false;
        $generator = function () use (&$yielded, &$invoked, $exception, $value) {
            try {
                $yielded = (yield new Delayed(self::TIMEOUT, $value));
                throw $exception;
            } finally {
                $invoked = true;
            }
        };

        try {
            await(new Coroutine($generator()));
        } catch (\Throwable $reason) {
            $this->assertSame($exception, $reason);
            $this->assertTrue($invoked);
            $this->assertSame($value, $yielded);
            return;
        }

        $this->fail("Coroutine should have failed");
    }

    /**
     * @depends testYieldPendingPromise
     * @depends testGeneratorThrowingExceptionWithFinallyFailsCoroutine
     */
    public function testGeneratorThrowingExceptionWithFinallyYieldingPendingPromise()
    {
        $exception = new \Exception;
        $value = 1;

        $generator = function () use (&$yielded, $exception, $value) {
            try {
                throw $exception;
            } finally {
                $yielded = yield new Delayed(self::TIMEOUT, $value);
            }
        };

        try {
            await(new Coroutine($generator()));
        } catch (\Throwable $reason) {
            $this->assertSame($exception, $reason);
            $this->assertSame($value, $yielded);
            return;
        }

        $this->fail("Coroutine should have failed");
    }

    /**
     * @depends testYieldPendingPromise
     * @depends testGeneratorThrowingExceptionWithFinallyFailsCoroutine
     */
    public function testGeneratorThrowingExceptionWithFinallyBlockThrowing()
    {
        $exception = new \Exception;

        $generator = function () use ($exception) {
            try {
                throw new \Exception;
            } finally {
                throw $exception;
            }

            yield; // Unreachable, but makes function a generator.
        };

        try {
            await(new Coroutine($generator()));
        } catch (\Throwable $reason) {
            $this->assertSame($exception, $reason);
            return;
        }

        $this->fail("Coroutine should have failed");
    }

    /**
     * @depends testGeneratorThrowingExceptionWithFinallyFailsCoroutine
     */
    public function testGeneratorThrowingExceptionWithFinallyBlockAndReturnThrowing()
    {
        $exception = new \Exception;

        $generator = function () use ($exception) {
            yield new Success;

            return call(function () use ($exception) {
                return new class($exception) {
                    private $exception;

                    public function __construct(\Throwable $exception)
                    {
                        $this->exception = $exception;
                    }

                    public function __destruct()
                    {
                        throw $this->exception;
                    }
                };
            });
        };

        try {
            await(new Coroutine($generator()));
        } catch (\Throwable $e) {
            $this->fail("Caught exception that shouldn't be thrown at that place.");
        }

        Loop::setErrorHandler(function (\Throwable $exception) use (&$reason): void {
            $reason = $exception;
        });

        delay(0); // Tick event loop to invoke error callback.

        $this->assertSame($exception, $reason);
    }

    /**
     * @depends testYieldSuccessfulPromise
     */
    public function testYieldConsecutiveSucceeded()
    {
        $count = 1000;
        $promise = new Success;

        $generator = function () use ($count, $promise) {
            for ($i = 0; $i < $count; ++$i) {
                yield $promise;
            }
            return $count;
        };

        $this->assertSame($count, await(new Coroutine($generator())));
    }

    /**
     * @depends testYieldFailedPromise
     */
    public function testYieldConsecutiveFailed()
    {
        $count = 1000;
        $promise = new Failure(new \Exception);

        $generator = function () use ($count, $promise) {
            for ($i = 0; $i < $count; ++$i) {
                try {
                    yield $promise;
                } catch (\Exception $exception) {
                    // Ignore and continue.
                }
            }

            return $count;
        };

        $this->assertSame($count, await(new Coroutine($generator())));
    }

    /**
     * @depends testYieldSuccessfulPromise
     */
    public function testFastInvalidGenerator()
    {
        $generator = function () {
            if (false) {
                yield new Success;
            }
        };

        $this->assertNull(await(new Coroutine($generator())));
    }

    public function testCoroutineFunction()
    {
        $callable = \Amp\coroutine(function () {
            yield;
        });

        $this->assertInstanceOf(Coroutine::class, $callable());
    }

    /**
     * @depends testCoroutineFunction
     */
    public function testCoroutineFunctionWithCallbackReturningPromise()
    {
        $value = 1;
        $promise = new Success($value);
        $callable = \Amp\coroutine(function ($value) {
            return $value;
        });

        /** @var Promise $promise */
        $promise = $callable($promise);

        $this->assertInstanceOf(Promise::class, $promise);

        $this->assertSame($value, await($promise));
    }

    /**
     * @depends testCoroutineFunction
     */
    public function testCoroutineFunctionWithNonGeneratorCallback()
    {
        $value = 1;
        $callable = \Amp\coroutine(function ($value) {
            return $value;
        });

        /** @var Promise $promise */
        $promise = $callable($value);

        $this->assertInstanceOf(Promise::class, $promise);

        $this->assertSame($value, await($promise));
    }

    /**
     * @depends testCoroutineFunction
     */
    public function testCoroutineFunctionWithThrowingCallback()
    {
        $exception = new \Exception;
        $callable = \Amp\coroutine(function () use ($exception) {
            throw $exception;
        });

        /** @var Promise $promise */
        $promise = $callable();

        $this->assertInstanceOf(Promise::class, $promise);

        try {
            await($promise);
        } catch (\Throwable $reason) {
            $this->assertSame($exception, $reason);
            return;
        }

        $this->fail("Coroutine should have failed");
    }

    /**
     * @depends testCoroutineFunction
     */
    public function testCoroutineFunctionWithSuccessReturnCallback()
    {
        $callable = \Amp\coroutine(function () {
            return new Success(42);
        });

        /** @var Promise $promise */
        $promise = $callable();

        $this->assertInstanceOf(Promise::class, $promise);

        $this->assertSame(42, await($promise));
    }

    public function testCoroutineResolvedWithReturn()
    {
        $value = 1;

        $generator = function () use ($value) {
            return $value;
            yield; // Unreachable, but makes function a coroutine.
        };

        $this->assertSame($value, await(new Coroutine($generator())));
    }

    /**
     * @depends testCoroutineResolvedWithReturn
     */
    public function testYieldFromGenerator()
    {
        $value = 1;

        $generator = function () use ($value) {
            $generator = function () use ($value) {
                return yield new Success($value);
            };

            return yield from $generator();
        };

        $this->assertSame($value, await(new Coroutine($generator())));
    }

    /**
     * @depends testCoroutineResolvedWithReturn
     */
    public function testFastReturningGenerator()
    {
        $value = 1;

        $generator = function () use ($value) {
            if (true) {
                return $value;
            }

            yield;

            return -$value;
        };

        $this->assertSame($value, await(new Coroutine($generator())));
    }

    public function testAsyncCoroutineFunctionWithFailure()
    {
        $coroutine = \Amp\asyncCoroutine(function ($value) {
            return new Failure(new TestException);
        });

        $coroutine(42);

        Loop::setErrorHandler(function (\Throwable $exception) use (&$reason): void {
            $reason = $exception;
        });

        delay(0); // Tick event loop to invoke error callback.

        $this->assertInstanceOf(TestException::class, $reason);
    }
}
