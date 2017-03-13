<?php

namespace Amp\Test;

use Amp;
use Amp\Coroutine;
use Amp\Failure;
use Amp\InvalidYieldError;
use Amp\Loop;
use Amp\Pause;
use Amp\Success;
use Amp\Promise;
use React\Promise\Promise as ReactPromise;

class CoroutineTest extends \PHPUnit\Framework\TestCase {
    const TIMEOUT = 100;

    public function testYieldSuccessfulPromise() {
        $value = 1;

        $generator = function () use (&$yielded, $value) {
            $yielded = yield new Success($value);
        };

        new Coroutine($generator());

        $this->assertSame($value, $yielded);
    }

    public function testYieldFailedPromise() {
        $exception = new \Exception;

        $generator = function () use (&$yielded, $exception) {
            $yielded = yield new Failure($exception);
        };

        $coroutine = new Coroutine($generator());

        $this->assertNull($yielded);

        $coroutine->when(function ($exception) use (&$reason) {
            $reason = $exception;
        });

        $this->assertSame($exception, $reason);
    }

    /**
     * @depends testYieldSuccessfulPromise
     */
    public function testYieldPendingPromise() {
        $value = 1;

        Loop::run(function () use (&$yielded, $value) {
            $generator = function () use (&$yielded, $value) {
                $yielded = yield new Pause(self::TIMEOUT, $value);
            };

            new Coroutine($generator());
        });

        $this->assertSame($value, $yielded);
    }

    public function testYieldPromiseArray() {
        Loop::run(function () {
            $value = 1;

            $generator = function () use (&$yielded, $value) {
                list($yielded) = yield [
                    new Success($value)
                ];
            };

            yield new Coroutine($generator());

            $this->assertSame($value, $yielded);
        });
    }

    public function testYieldNonPromiseArray() {
        $this->expectException(InvalidYieldError::class);

        Loop::run(function () {
            $value = 1;

            $generator = function () use (&$yielded, $value) {
                list($yielded) = yield [
                    $value
                ];
            };

            yield new Coroutine($generator());
        });
    }

    /**
     * @depends testYieldFailedPromise
     */
    public function testCatchingFailedPromiseException() {
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

        new Coroutine($generator());

        if ($fail) {
            $this->fail("Failed promise reason not thrown into generator");
        }

    }

    public function testInvalidYield() {
        $generator = function () {
            yield 1;
        };

        $coroutine = new Coroutine($generator());

        $coroutine->when(function ($exception) use (&$reason) {
            $reason = $exception;
        });

        $this->assertInstanceOf(InvalidYieldError::class, $reason);
    }

    /**
     * @depends testInvalidYield
     */
    public function testInvalidYieldAfterYieldPromise() {
        $generator = function () {
            yield new Success;
            yield 1;
        };

        $coroutine = new Coroutine($generator());

        $coroutine->when(function ($exception) use (&$reason) {
            $reason = $exception;
        });

        $this->assertInstanceOf(InvalidYieldError::class, $reason);
    }

    /**
     * @depends testInvalidYield
     */
    public function testInvalidYieldCatchingThrownException() {
        $generator = function () {
            try {
                yield 1;
            } catch (\Error $exception) {
                // No further yields.
            }
        };

        $coroutine = new Coroutine($generator());

        $coroutine->when(function ($exception) use (&$reason) {
            $reason = $exception;
        });

        $this->assertInstanceOf(InvalidYieldError::class, $reason);
    }

    /**
     * @depends testInvalidYieldCatchingThrownException
     */
    public function testInvalidYieldCatchingThrownExceptionAndYieldingAgain() {
        $generator = function () {
            try {
                yield 1;
            } catch (\Error $exception) {
                yield new Success;
            }
        };

        $coroutine = new Coroutine($generator());

        $coroutine->when(function ($exception) use (&$reason) {
            $reason = $exception;
        });

        $this->assertInstanceOf(InvalidYieldError::class, $reason);
    }

    /**
     * @depends testInvalidYieldCatchingThrownException
     */
    public function testInvalidYieldCatchingThrownExceptionAndThrowing() {
        $exception = new \Exception;
        $generator = function () use ($exception) {
            try {
                yield 1;
            } catch (\Error $error) {
                throw $exception;
            }
        };

        $coroutine = new Coroutine($generator());

        $coroutine->when(function ($exception) use (&$reason) {
            $reason = $exception;
        });

        $this->assertInstanceOf(InvalidYieldError::class, $reason);
        $this->assertSame($exception, $reason->getPrevious());
    }

    /**
     * @depends testYieldFailedPromise
     */
    public function testCatchingFailedPromiseExceptionWithNoFurtherYields() {
        $exception = new \Exception;

        $generator = function () use ($exception) {
            try {
                yield new Failure($exception);
            } catch (\Exception $exception) {
                // No further yields in generator.
            }
        };

        $coroutine = new Coroutine($generator());

        $coroutine->when(function ($exception, $value) use (&$reason, &$result) {
            $reason = $exception;
            $result = $value;
        });

        $this->assertNull($reason);
        $this->assertNull($result);
    }

    public function testGeneratorThrowingExceptionFailsCoroutine() {
        $exception = new \Exception;

        $generator = function () use ($exception) {
            throw $exception;
            yield;
        };

        $coroutine = new Coroutine($generator());

        $coroutine->when(function ($exception) use (&$reason) {
            $reason = $exception;
        });

        $this->assertSame($exception, $reason);
    }

    /**
     * @depends testGeneratorThrowingExceptionFailsCoroutine
     */
    public function testGeneratorThrowingExceptionWithFinallyFailsCoroutine() {
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

        $coroutine = new Coroutine($generator());

        $coroutine->when(function ($exception) use (&$reason) {
            $reason = $exception;
        });

        $this->assertSame($exception, $reason);
        $this->assertTrue($invoked);
    }

    /**
     * @depends testYieldFailedPromise
     * @depends testGeneratorThrowingExceptionWithFinallyFailsCoroutine
     */
    public function testGeneratorYieldingFailedPromiseWithFinallyFailsCoroutine() {
        $exception = new \Exception;

        $invoked = false;
        $generator = function () use (&$invoked, $exception) {
            try {
                yield new Failure($exception);
            } finally {
                $invoked = true;
            }
        };

        $coroutine = new Coroutine($generator());

        $coroutine->when(function ($exception) use (&$reason) {
            $reason = $exception;
        });

        $this->assertSame($exception, $reason);
        $this->assertTrue($invoked);
    }

    /**
     * @depends testGeneratorThrowingExceptionFailsCoroutine
     */
    public function testGeneratorThrowingExceptionAfterPendingPromiseWithFinallyFailsCoroutine() {
        $exception = new \Exception;
        $value = 1;

        Loop::run(function () use (&$yielded, &$invoked, &$reason, $exception, $value) {
            $invoked = false;
            $generator = function () use (&$yielded, &$invoked, $exception, $value) {
                try {
                    $yielded = (yield new Pause(self::TIMEOUT, $value));
                    throw $exception;
                } finally {
                    $invoked = true;
                }
            };

            $coroutine = new Coroutine($generator());

            $coroutine->when(function ($exception) use (&$reason) {
                $reason = $exception;
            });
        });

        $this->assertSame($exception, $reason);
        $this->assertTrue($invoked);
        $this->assertSame($value, $yielded);
    }

    /**
     * Note that yielding in a finally block is not recommended.
     *
     * @depends testYieldPendingPromise
     * @depends testGeneratorThrowingExceptionWithFinallyFailsCoroutine
     */
    public function testGeneratorThrowingExceptionWithFinallyYieldingPendingPromise() {
        $exception = new \Exception;
        $value = 1;

        Loop::run(function () use (&$yielded, &$reason, $exception, $value) {
            $generator = function () use (&$yielded, $exception, $value) {
                try {
                    throw $exception;
                } finally {
                    $yielded = yield new Pause(self::TIMEOUT, $value);
                }
            };

            $coroutine = new Coroutine($generator());

            $coroutine->when(function ($exception) use (&$reason) {
                $reason = $exception;
            });
        });

        $this->assertSame($value, $yielded);
        $this->assertSame($exception, $reason);
    }

    /**
     * @depends testYieldPendingPromise
     * @depends testGeneratorThrowingExceptionWithFinallyFailsCoroutine
     */
    public function testGeneratorThrowingExceptionWithFinallyBlockThrowing() {
        $exception = new \Exception;

        $generator = function () use ($exception) {
            try {
                throw new \Exception;
            } finally {
                throw $exception;
            }

            yield; // Unreachable, but makes function a generator.
        };

        $coroutine = new Coroutine($generator());

        $coroutine->when(function ($exception) use (&$reason) {
            $reason = $exception;
        });

        $this->assertSame($exception, $reason);
    }

    /**
     * @depends testYieldSuccessfulPromise
     */
    public function testYieldConsecutiveSucceeded() {
        $invoked = false;
        Loop::run(function () use (&$invoked) {
            $count = 1000;
            $promise = new Success;

            $generator = function () use ($count, $promise) {
                for ($i = 0; $i < $count; ++$i) {
                    yield $promise;
                }
            };

            $coroutine = new Coroutine($generator());

            $coroutine->when(function () use (&$invoked) {
                $invoked = true;
            });
        });

        $this->assertTrue($invoked);
    }

    /**
     * @depends testYieldFailedPromise
     */
    public function testYieldConsecutiveFailed() {
        $invoked = false;
        Loop::run(function () use (&$invoked) {
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
            };

            $coroutine = new Coroutine($generator());

            $coroutine->when(function () use (&$invoked) {
                $invoked = true;
            });
        });
    }

    /**
     * @depends testYieldSuccessfulPromise
     */
    public function testFastInvalidGenerator() {
        $generator = function () {
            if (false) {
                yield new Success;
            }
        };

        $coroutine = new Coroutine($generator());

        $invoked = false;
        $coroutine->when(function () use (&$invoked) {
            $invoked = true;
        });

        $this->assertTrue($invoked);
    }

    public function testCoroutineFunction() {
        $callable = Amp\coroutine(function () {
            yield;
        });

        $this->assertInstanceOf(Coroutine::class, $callable());
    }

    /**
     * @depends testCoroutineFunction
     */
    public function testCoroutineFunctionWithCallbackReturningPromise() {
        $value = 1;
        $promise = new Success($value);
        $callable = Amp\coroutine(function ($value) {
            return $value;
        });

        /** @var Promise $promise */
        $promise = $callable($promise);

        $this->assertInstanceOf(Promise::class, $promise);

        $promise->when(function ($exception, $value) use (&$reason, &$result) {
            $reason = $exception;
            $result = $value;
        });

        $this->assertNull($reason);
        $this->assertSame($value, $result);
    }

    /**
     * @depends testCoroutineFunction
     */
    public function testCoroutineFunctionWithNonGeneratorCallback() {
        $value = 1;
        $callable = Amp\coroutine(function ($value) {
            return $value;
        });

        /** @var Promise $promise */
        $promise = $callable($value);

        $this->assertInstanceOf(Promise::class, $promise);

        $promise->when(function ($exception, $value) use (&$reason, &$result) {
            $reason = $exception;
            $result = $value;
        });

        $this->assertNull($reason);
        $this->assertSame($value, $result);
    }

    /**
     * @depends testCoroutineFunction
     */
    public function testCoroutineFunctionWithThrowingCallback() {
        $exception = new \Exception;
        $callable = Amp\coroutine(function () use ($exception) {
            throw $exception;
        });

        /** @var Promise $promise */
        $promise = $callable();

        $this->assertInstanceOf(Promise::class, $promise);

        $promise->when(function ($exception, $value) use (&$reason, &$result) {
            $reason = $exception;
            $result = $value;
        });

        $this->assertSame($exception, $reason);
        $this->assertNull($result);
    }

    /**
     * @depends testCoroutineFunction
     */
    public function testCoroutineFunctionWithSuccessReturnCallback() {
        $callable = Amp\coroutine(function () {
            return new Success(42);
        });

        /** @var Promise $promise */
        $promise = $callable();

        $this->assertInstanceOf(Promise::class, $promise);

        $promise->when(function ($exception, $value) use (&$reason, &$result) {
            $reason = $exception;
            $result = $value;
        });

        $this->assertNull($reason);
        $this->assertSame(42, $result);
    }

    public function testCoroutineResolvedWithReturn() {
        $value = 1;

        $generator = function () use ($value) {
            return $value;
            yield; // Unreachable, but makes function a coroutine.
        };

        $coroutine = new Coroutine($generator());

        $coroutine->when(function ($exception, $value) use (&$reason, &$result) {
            $reason = $exception;
            $result = $value;
        });

        $this->assertNull($reason);
        $this->assertSame($value, $result);
    }

    /**
     * @depends testCoroutineResolvedWithReturn
     */
    public function testYieldFromGenerator() {
        $value = 1;

        $generator = function () use ($value) {
            $generator = function () use ($value) {
                return yield new Success($value);
            };

            return yield from $generator();
        };

        $coroutine = new Coroutine($generator());

        $coroutine->when(function ($exception, $value) use (&$reason, &$result) {
            $reason = $exception;
            $result = $value;
        });

        $this->assertNull($reason);
        $this->assertSame($value, $result);
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

        $coroutine = new Coroutine($generator());

        $coroutine->when(function ($exception, $value) use (&$reason, &$result) {
            $reason = $exception;
            $result = $value;
        });

        $this->assertNull($reason);
        $this->assertSame($value, $result);
    }

    public function testYieldingFulfilledReactPromise() {
        $value = 1;
        $promise = new ReactPromise(function ($resolve, $reject) use ($value) {
            $resolve($value);
        });

        $generator = function () use ($promise) {
            return yield $promise;
        };

        $coroutine = new Coroutine($generator());

        $coroutine->when(function ($exception, $value) use (&$reason, &$result) {
            $reason = $exception;
            $result = $value;
        });

        $this->assertNull($reason);
        $this->assertSame($value, $result);
    }

    public function testYieldingFulfilledReactPromiseAfterInteropPromise() {
        $value = 1;
        $promise = new ReactPromise(function ($resolve, $reject) use ($value) {
            $resolve($value);
        });

        $generator = function () use ($promise) {
            $value = yield new Success(-1);
            return yield $promise;
        };

        $coroutine = new Coroutine($generator());

        $coroutine->when(function ($exception, $value) use (&$reason, &$result) {
            $reason = $exception;
            $result = $value;
        });

        $this->assertNull($reason);
        $this->assertSame($value, $result);
    }

    public function testYieldingRejectedReactPromise() {
        $exception = new \Exception;
        $promise = new ReactPromise(function ($resolve, $reject) use ($exception) {
            $reject($exception);
        });

        $generator = function () use ($promise) {
            return yield $promise;
        };

        $coroutine = new Coroutine($generator());

        $coroutine->when(function ($exception, $value) use (&$reason, &$result) {
            $reason = $exception;
            $result = $value;
        });

        $this->assertSame($reason, $exception);
        $this->assertNull($result);
    }

    public function testYieldingRejectedReactPromiseAfterInteropPromise() {
        $exception = new \Exception;
        $promise = new ReactPromise(function ($resolve, $reject) use ($exception) {
            $reject($exception);
        });

        $generator = function () use ($promise) {
            $value = yield new Success(-1);
            return yield $promise;
        };

        $coroutine = new Coroutine($generator());

        $coroutine->when(function ($exception, $value) use (&$reason, &$result) {
            $reason = $exception;
            $result = $value;
        });

        $this->assertSame($reason, $exception);
        $this->assertNull($result);
    }

    public function testReturnFulfilledReactPromise() {
        $value = 1;
        $promise = new ReactPromise(function ($resolve, $reject) use ($value) {
            $resolve($value);
        });

        $generator = function () use ($promise) {
            return $promise;
            yield; // Unreachable, but makes function a generator.
        };

        $coroutine = new Coroutine($generator());

        $coroutine->when(function ($exception, $value) use (&$reason, &$result) {
            $reason = $exception;
            $result = $value;
        });

        $this->assertNull($reason);
        $this->assertSame($value, $result);
    }

    public function testReturningRejectedReactPromise() {
        $exception = new \Exception;
        $promise = new ReactPromise(function ($resolve, $reject) use ($exception) {
            $reject($exception);
        });

        $generator = function () use ($promise) {
            return $promise;
            yield; // Unreachable, but makes function a generator.
        };

        $coroutine = new Coroutine($generator());

        $coroutine->when(function ($exception, $value) use (&$reason, &$result) {
            $reason = $exception;
            $result = $value;
        });

        $this->assertSame($reason, $exception);
        $this->assertNull($result);
    }
}
