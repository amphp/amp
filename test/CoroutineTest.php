<?php

declare(strict_types=1);

namespace Icicle\Tests\Coroutine;

use Amp;
use Amp\Coroutine;
use Amp\Failure;
use Amp\InvalidYieldError;
use Amp\Pause;
use Amp\Success;
use Interop\Async\Awaitable;
use Interop\Async\Loop;

class CoroutineTest extends \PHPUnit_Framework_TestCase {
    const TIMEOUT = 100;

    public function testYieldSuccessfulAwaitable() {
        $value = 1;

        $generator = function () use (&$yielded, $value) {
            $yielded = (yield new Success($value));
        };

        $coroutine = new Coroutine($generator());

        $this->assertSame($value, $yielded);
    }

    public function testYieldFailedAwaitable() {
        $exception = new \Exception;

        $generator = function () use (&$yielded, $exception) {
            $yielded = (yield new Failure($exception));
        };

        $coroutine = new Coroutine($generator());

        $this->assertNull($yielded);

        $coroutine->when(function ($exception) use (&$reason) {
            $reason = $exception;
        });

        $this->assertSame($exception, $reason);
    }

    /**
     * @depends testYieldSuccessfulAwaitable
     */
    public function testYieldPendingAwaitable() {
        $value = 1;

        Loop::execute(function () use (&$yielded, $value) {
            $generator = function () use (&$yielded, $value) {
                $yielded = (yield new Pause(self::TIMEOUT, $value));
            };

            $coroutine = new Coroutine($generator());
        });

        $this->assertSame($value, $yielded);
    }

    /**
     * @depends testYieldFailedAwaitable
     */
    public function testCatchingFailedAwaitableException() {
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

        $coroutine = new Coroutine($generator());

        if ($fail) {
            $this->fail("Failed awaitable reason not thrown into generator");
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
    public function testInvalidYieldAfterYieldAwaitable() {
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
    public function testCatchesExceptionAfterInvalidYield() {
        $generator = function () {
            try {
                yield 1;
            } catch (\Exception $exception) {
                return 1;
            }
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
    public function testThrowAfterInvalidYield() {
        $exception = new \Exception;

        $generator = function () use ($exception) {
            try {
                yield 1;
            } catch (\Throwable $reason) {
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
     * @depends testYieldFailedAwaitable
     */
    public function testCatchingFailedAwaitableExceptionWithNoFurtherYields() {
        $exception = new \Exception;

        $generator = function () use ($exception) {
            try {
                yield new Failure($exception);
            } catch (\Exception $exception) {
                // No further yields in generator.
            }
        };

        $coroutine = new Coroutine($generator());

        $coroutine->when(function ($exception, $value) use (&$result) {
            $result = $value;
        });

        $this->assertNull($result);
    }

    public function testGeneratorThrowingExceptionFailsCoroutine() {
        $exception = new \Exception;

        $generator = function () use ($exception) {
            throw $exception;
            yield;
        };

        $coroutine = new Coroutine($generator());

        $coroutine->when(function ($exception, $value) use (&$reason) {
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

        $coroutine->when(function ($exception, $value) use (&$reason) {
            $reason = $exception;
        });

        $this->assertSame($exception, $reason);
        $this->assertTrue($invoked);
    }

    /**
     * @depends testYieldFailedAwaitable
     * @depends testGeneratorThrowingExceptionWithFinallyFailsCoroutine
     */
    public function testGeneratorYieldingFailedAwaitableWithFinallyFailsCoroutine() {
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

        $coroutine->when(function ($exception, $value) use (&$reason) {
            $reason = $exception;
        });

        $this->assertSame($exception, $reason);
        $this->assertTrue($invoked);
    }

    /**
     * @depends testGeneratorThrowingExceptionFailsCoroutine
     */
    public function testGeneratorThrowingExceptionAfterPendingAwaitableWithFinallyFailsCoroutine() {
        $exception = new \Exception;
        $value = 1;

        Loop::execute(function () use (&$yielded, &$invoked, &$reason, $exception, $value) {
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

            $coroutine->when(function ($exception, $value) use (&$reason) {
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
     * @depends testYieldPendingAwaitable
     * @depends testGeneratorThrowingExceptionWithFinallyFailsCoroutine
     */
    public function testGeneratorThrowingExceptionWithFinallyYieldingPendingAwaitable() {
        $exception = new \Exception;
        $value = 1;

        Loop::execute(function () use (&$yielded, &$reason, $exception, $value) {
            $generator = function () use (&$yielded, $exception, $value) {
                try {
                    throw $exception;
                } finally {
                    $yielded = (yield new Pause(self::TIMEOUT, $value));
                }
            };

            $coroutine = new Coroutine($generator());

            $coroutine->when(function ($exception, $value) use (&$reason) {
                $reason = $exception;
            });
        });

        $this->assertSame($value, $yielded);
        $this->assertSame($exception, $reason);
    }

    /**
     * @depends testYieldPendingAwaitable
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

        $coroutine->when(function ($exception, $value) use (&$reason) {
            $reason = $exception;
        });

        $this->assertSame($exception, $reason);
    }

    /**
     * @depends testYieldSuccessfulAwaitable
     */
    public function testYieldConsecutiveSucceeded() {
        $invoked = false;
        Loop::execute(function () use (&$invoked) {
            $count = 1000;
            $awaitable = new Success;

            $generator = function () use ($count, $awaitable) {
                for ($i = 0; $i < $count; ++$i) {
                    yield $awaitable;
                }
            };

            $coroutine = new Coroutine($generator());

            $coroutine->when(function ($exception, $value) use (&$invoked) {
                $invoked = true;
            });
        });

        $this->assertTrue($invoked);
    }

    /**
     * @depends testYieldFailedAwaitable
     */
    public function testYieldConsecutiveFailed() {
        $invoked = false;
        Loop::execute(function () use (&$invoked) {
            $count = 1000;
            $awaitable = new Failure(new \Exception);

            $generator = function () use ($count, $awaitable) {
                for ($i = 0; $i < $count; ++$i) {
                    try {
                        yield $awaitable;
                    } catch (\Exception $exception) {
                        // Ignore and continue.
                    }
                }
            };

            $coroutine = new Coroutine($generator());

            $coroutine->when(function ($exception, $value) use (&$invoked) {
                $invoked = true;
            });
        });
    }

    /**
     * @depends testYieldSuccessfulAwaitable
     */
    public function testFastInvalidGenerator() {
        $generator = function () {
            if (false) {
                yield new Success;
            }
        };

        $coroutine = new Coroutine($generator());

        $invoked = false;
        $coroutine->when(function ($exception, $value) use (&$invoked) {
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
    public function testCoroutineFunctionWithCallbackReturningAwaitable() {
        $value = 1;
        $awaitable = new Success($value);
        $callable = Amp\coroutine(function ($value) {
            return $value;
        });
        
        $awaitable = $callable($awaitable);
        
        $this->assertInstanceOf(Awaitable::class, $awaitable);
        
        $awaitable->when(function ($exception, $value) use (&$result) {
            $result = $value;
        });
        
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
        
        $awaitable = $callable($value);
        
        $this->assertInstanceOf(Awaitable::class, $awaitable);
    
        $awaitable->when(function ($exception, $value) use (&$result) {
            $result = $value;
        });
    
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
        
        $awaitable = $callable();
        
        $this->assertInstanceOf(Awaitable::class, $awaitable);
    
        $awaitable->when(function ($exception, $value) use (&$reason) {
            $reason = $exception;
        });
    
        $this->assertSame($exception, $reason);
    }
    
    public function testCoroutineResolvedWithReturn() {
        $value = 1;
        
        $generator = function () use ($value) {
            return $value;
            yield; // Unreachable, but makes function a coroutine.
        };
        
        $coroutine = new Coroutine($generator());
        
        $coroutine->when(function ($exception, $value) use (&$result) {
            $result = $value;
        });
        
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
        
        $coroutine->when(function ($exception, $value) use (&$result) {
            $result = $value;
        });
        
        
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
        
        $coroutine->when(function ($exception, $value) use (&$result) {
            $result = $value;
        });
        
        $this->assertSame($value, $result);
    }
}
