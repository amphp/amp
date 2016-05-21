<?php

namespace Amp\Awaitable;

use Interop\Async\Awaitable;
use Interop\Async\Loop;
use Interop\Async\LoopDriver;

if (!\function_exists(__NAMESPACE__ . '\resolve')) {
    /**
     * Return a awaitable using the given value. There are four possible outcomes depending on the type of $value:
     * (1) \Interop\Async\Awaitable: The awaitable is returned without modification.
     * (2) \Generator: The generator is used to create a coroutine.
     * (3) callable: The callable is invoked with no arguments. The return value is pass through this function agian.
     * (4) All other types: A successful awaitable is returned using the given value as the result.
     *
     * @param mixed $value
     *
     * @return \Interop\Async\Awaitable
     */
    function resolve($value = null) {
        if ($value instanceof Awaitable) {
            return $value;
        }

        if ($value instanceof \Generator) {
            return new Coroutine($value);
        }

        if (\is_callable($value)) {
            return resolve($value());
        }

        return new Success($value);
    }
    
    /**
     * Create a new failed awaitable using the given exception.
     *
     * @param \Throwable|\Exception $reason
     *
     * @return \Interop\Async\Awaitable
     */
    function fail($reason) {
        return new Failure($reason);
    }

    /**
     * Returns a new function that when invoked runs the Generator returned by $worker as a coroutine.
     *
     * @param callable(mixed ...$args): \Generator $worker
     *
     * @return callable(mixed ...$args): \Amp\Awaitable\Coroutine
     */
    function coroutine(callable $worker) {
        return function (/* ...$args */) use ($worker) {
            $generator = \call_user_func_array($worker, \func_get_args());

            if (!$generator instanceof \Generator) {
                throw new \LogicException('The callable did not return a Generator');
            }

            return new Coroutine($generator);
        };
    }

    /**
     * Registers a callback that will forward the failure reason to the Loop error handler if the awaitable fails.
     *
     * @param \Interop\Async\Awaitable $awaitable
     */
    function rethrow(Awaitable $awaitable) {
        /**
         * @param \Throwable|\Exception $exception
         * @param mixed $value
         */
        $awaitable->when(function ($exception = null, $value = null) {
            if ($exception) {
                /** @var \Throwable|\Exception $exception */
                throw $exception;
            }
        });
    }

    /**
     * Runs the event loop until the awaitable is resolved. Should not be called within a running event loop.
     *
     * @param \Interop\Async\Awaitable $awaitable
     * @param \Interop\Async\LoopDriver|null $driver
     *
     * @return mixed Awaitable success value.
     *
     * @throws \Throwable|\Exception Awaitable failure reason.
     */
    function wait(Awaitable $awaitable, LoopDriver $driver = null) {
        Loop::execute(function () use (&$value, &$exception, $awaitable) {
            $awaitable->when(function ($e = null, $v = null) use (&$value, &$exception) {
                Loop::stop();
                $exception = $e;
                $value = $v;
            });
        }, $driver);

        if ($exception) {
            throw $exception;
        }

        return $value;
    }

    /**
     * Pipe the promised value through the specified functor once it resolves.
     *
     * @param \Interop\Async\Awaitable $awaitable
     * @param callable(mixed $value): mixed $functor
     *
     * @return \Interop\Async\Awaitable
     */
    function pipe(Awaitable $awaitable, callable $functor) {
        $deferred = new Deferred();

        $awaitable->when(function ($exception = null, $value = null) use ($deferred, $functor) {
            if ($exception) {
                $deferred->fail($exception);
                return;
            }

            try {
                $deferred->resolve($functor($value));
            } catch (\Throwable $exception) {
                $deferred->fail($exception);
            } catch (\Exception $exception) {
                $deferred->fail($exception);
            }
        });

        return $deferred->getAwaitable();
    }

    /**
     * @param \Interop\Async\Awaitable $awaitable
     * @param callable(\Throwable|\Exception $exception): mixed $functor
     *
     * @return \Interop\Async\Awaitable
     */
    function capture(Awaitable $awaitable, callable $functor) {
        $deferred = new Deferred();

        $awaitable->when(function ($exception = null, $value = null) use ($deferred, $functor) {
            if (!$exception) {
                $deferred->resolve($value);
                return;
            }

            try {
                $deferred->resolve($functor($exception));
            } catch (\Throwable $exception) {
                $deferred->fail($exception);
            } catch (\Exception $exception) {
                $deferred->fail($exception);
            }
        });

        return $deferred->getAwaitable();
    }

    /**
     * Create an artificial timeout for any Awaitable.
     *
     * If the timeout expires before the awaitable is resolved, the returned awaitable fails with an instance of
     * \Amp\Awaitable\Exception\TimeoutException.
     *
     * @param \Interop\Async\Awaitable $awaitable
     * @param int $timeout Timeout in milliseconds.
     *
     * @return \Interop\Async\Awaitable
     */
    function timeout(Awaitable $awaitable, $timeout) {
        $deferred = new Deferred();

        $watcher = Loop::delay(function () use ($deferred) {
            $deferred->fail(new Exception\TimeoutException());
        }, $timeout);
    
        $onResolved = function () use ($awaitable, $deferred, $watcher) {
            Loop::cancel($watcher);
            $deferred->resolve($awaitable);
        };

        $awaitable->when($onResolved);

        return $deferred->getAwaitable();
    }

    /**
     * Artificially delays the success of an awaitable $delay milliseconds after the awaitable succeeds. If the
     * awaitable fails, the returned awaitable fails immediately.
     *
     * @param \Interop\Async\Awaitable $awaitable
     * @param int $delay Delay in milliseconds.
     *
     * @return \Interop\Async\Awaitable
     */
    function delay(Awaitable $awaitable, $delay) {
        $deferred = new Deferred();

        $onResolved = function ($exception = null) use ($awaitable, $deferred, $delay) {
            if ($exception) {
                $deferred->fail($exception);
                return;
            }

            Loop::delay(function () use ($awaitable, $deferred) {
                $deferred->resolve($awaitable);
            }, $delay);
        };

        $awaitable->when($onResolved);

        return $deferred->getAwaitable();
    }

    /**
     * Returns a awaitable that calls $promisor only when the result of the awaitable is requested (e.g., then() or
     * done() is called on the returned awaitable). $promisor can return a awaitable or any value. If $promisor throws
     * an exception, the returned awaitable is rejected with that exception.
     *
     * @param callable $promisor
     * @param mixed ...$args
     *
     * @return \Interop\Async\Awaitable
     */
    function lazy(callable $promisor /* ...$args */) {
        $args = \array_slice(\func_get_args(), 1);

        if (empty($args)) {
            return new Internal\Lazy($promisor);
        }

        return new Internal\Lazy(function () use ($promisor, $args) {
            return \call_user_func_array($promisor, $args);
        });
    }

    /**
     * Transforms a function that takes a callback into a function that returns a awaitable. The awaitable is fulfilled
     * with an array of the parameters that would have been passed to the callback function.
     *
     * @param callable $worker Function that normally accepts a callback.
     * @param int $index Position of callback in $worker argument list (0-indexed).
     *
     * @return callable
     */
    function promisify(callable $worker, $index = 0) {
        return function (/* ...$args */) use ($worker, $index) {
            $args = \func_get_args();

            $deferred = new Deferred();

            $callback = function (/* ...$args */) use ($deferred) {
                $deferred->resolve(\func_get_args());
            };

            if (\count($args) < $index) {
                throw new \InvalidArgumentException('Too few arguments given to function');
            }

            \array_splice($args, $index, 0, [$callback]);

            \call_user_func_array($worker, $args);

            return $deferred->getAwaitable();
        };
    }
    
    /**
     * Adapts any object with a then(callable $onFulfilled, callable $onRejected) method to a awaitable usable by
     * components depending on placeholders implementing Awaitable.
     *
     * @param object $thenable Object with a then() method.
     *
     * @return \Interop\Async\Awaitable Awaitable resolved by the $thenable object.
     */
    function adapt($thenable) {
        if (!\is_object($thenable) || !\method_exists($thenable, 'then')) {
            return fail(new \InvalidArgumentException('Must provide an object with a then() method'));
        }
        
        return new Promise(function (callable $resolve, callable $fail) use ($thenable) {
            $thenable->then($resolve, $fail);
        });
    }

    /**
     * Wraps the given callable $worker in a awaitable aware function that has the same number of arguments as $worker,
     * but those arguments may be awaitables for the future argument value or just values. The returned function will
     * return a awaitable for the return value of $worker and will never throw. The $worker function will not be called
     * until each awaitable given as an argument is fulfilled. If any awaitable provided as an argument fails, the
     * awaitable returned by the returned function will be failed for the same reason. The awaitable succeeds with
     * the return value of $worker or failed if $worker throws.
     *
     * @param callable $worker
     *
     * @return callable
     */
    function lift(callable $worker) {
        /**
         * @param mixed ...$args Awaitables or values.
         *
         * @return \Interop\Async\Awaitable
         */
        return function (/* ...$args */) use ($worker) {
            $args = \func_get_args();

            if (1 === \count($args)) {
                return pipe(resolve($args[0]), $worker);
            }

            return pipe(all($args), function (array $args) use ($worker) {
                return \call_user_func_array($worker, $args);
            });
        };
    }

    /**
     * Returns a awaitable that is resolved when all awaitables are resolved. The returned awaitable will not reject by
     * itself (only if cancelled). Returned awaitable succeeds with an array of resolved awaitables, with keys
     * identical and corresponding to the original given array.
     *
     * @param mixed[] $awaitables Awaitables or values (passed through resolve() to create awaitables).
     *
     * @return \Interop\Async\Awaitable
     */
    function settle(array $awaitables) {
        if (empty($awaitables)) {
            return resolve([]);
        }

        $deferred = new Deferred();

        $pending = \count($awaitables);

        $onResolved = function () use (&$awaitables, &$pending, $deferred) {
            if (0 === --$pending) {
                $deferred->resolve($awaitables);
            }
        };

        foreach ($awaitables as &$awaitable) {
            $awaitable = resolve($awaitable);
            $awaitable->when($onResolved);
        }

        return $deferred->getAwaitable();
    }
    
    /**
     * Returns a awaitable that succeeds when all awaitables succeed, and fails if any awaitable fails. Returned
     * awaitable succeeds with an array of values used to succeed each contained awaitable, with keys corresponding to
     * the array of awaitables.
     *
     * @param mixed[] $awaitables Awaitables or values (passed through resolve() to create awaitables).
     *
     * @return \Interop\Async\Awaitable
     */
    function all(array $awaitables) {
        if (empty($awaitables)) {
            return resolve([]);
        }

        $deferred = new Deferred();

        $pending = \count($awaitables);
        $values = [];

        foreach ($awaitables as $key => $awaitable) {
            $onResolved = function ($exception = null, $value = null) use ($key, &$values, &$pending, $deferred) {
                if ($exception) {
                    $deferred->fail($exception);
                    return;
                }

                $values[$key] = $value;
                if (0 === --$pending) {
                    $deferred->resolve($values);
                }
            };

            resolve($awaitable)->when($onResolved);
        }

        return $deferred->getAwaitable();
    }
    
    /**
     * Returns a awaitable that succeeds when any awaitable succeeds, and fails only if all awaitables fail.
     *
     * @param mixed[] $awaitables Awaitables or values (passed through resolve() to create awaitables).
     *
     * @return \Interop\Async\Awaitable
     */
    function any(array $awaitables) {
        if (empty($awaitables)) {
            return fail(new \InvalidArgumentException('No awaitables provided'));
        }

        $deferred = new Deferred();

        $pending = \count($awaitables);
        $exceptions = [];

        foreach ($awaitables as $key => $awaitable) {
            $onResolved = function ($exception = null, $value = null) use ($key, &$exceptions, &$pending, $deferred) {
                if (!$exception) {
                    $deferred->resolve($value);
                    return;
                }

                $exceptions[$key] = $exception;
                if (0 === --$pending) {
                    $deferred->fail(new Exception\MultiReasonException($exceptions));
                }
            };

            resolve($awaitable)->when($onResolved);
        }

        return $deferred->getAwaitable();
    }
    
    /**
     * Returns a awaitable that succeeds when $required number of awaitables succeed. The awaitable fails if $required
     * number of awaitables can no longer succeed.
     *
     * @param mixed[] $awaitables Awaitables or values (passed through resolve() to create awaitables).
     * @param int $required Number of awaitables that must succeed to succeed the returned awaitable.
     *
     * @return \Interop\Async\Awaitable
     */
    function some(array $awaitables, $required) {
        $required = (int) $required;
        
        if (0 >= $required) {
            return resolve([]);
        }

        $pending = \count($awaitables);
        
        if ($required > $pending) {
            return fail(new \InvalidArgumentException('Too few awaitables provided'));
        }

        $deferred = new Deferred();

        $required = \min($pending, $required);
        $values = [];
        $exceptions = [];

        foreach ($awaitables as $key => $awaitable) {
            $onResolved = function ($exception = null, $value = null) use (
                &$key, &$values, &$exceptions, &$pending, &$required, $deferred
            ) {
                if ($exception) {
                    $exceptions[$key] = $exception;
                    if ($required > --$pending) {
                        $deferred->fail(new Exception\MultiReasonException($exceptions));
                    }
                    return;
                }

                $values[$key] = $value;
                --$pending;
                if (0 === --$required) {
                    $deferred->resolve($values);
                }
            };

            resolve($awaitable)->when($onResolved);
        }

        return $deferred->getAwaitable();
    }
    
    /**
     * Returns a awaitable that succeeds or fails when the first awaitable succeeds or fails.
     *
     * @param mixed[] $awaitables Awaitables or values (passed through resolve() to create awaitables).
     *
     * @return \Interop\Async\Awaitable
     */
    function choose(array $awaitables) {
        if (empty($awaitables)) {
            return fail(new \InvalidArgumentException('No awaitables provided'));
        }

        $deferred = new Deferred();

        foreach ($awaitables as $awaitable) {
            resolve($awaitable)->when(function ($exception = null, $value = null) use ($deferred) {
                if ($exception) {
                    $deferred->fail($exception);
                    return;
                }

                $deferred->resolve($value);
            });
        }

        return $deferred->getAwaitable();
    }

    /**
     * Maps the callback to each awaitable as it succeeds. Returns an array of awaitables resolved by the return
     * callback value of the callback function. The callback may return awaitables or throw exceptions to fail
     * awaitables in the array. If a awaitable in the passed array fails, the callback will not be called and the
     * awaitable in the array fails for the same reason. Tip: Use all() or settle() to determine when all
     * awaitables in the array have been resolved.
     *
     * @param callable(mixed $value): mixed $callback
     * @param mixed[] ...$awaitables Awaitables or values (passed through resolve() to create awaitables).
     *
     * @return \Interop\Async\Awaitable[] Array of awaitables resolved with the result of the mapped function.
     */
    function map(callable $callback /* array ...$awaitables */) {
        $args = \func_get_args();
        $args[0] = lift($args[0]);

        return \call_user_func_array('array_map', $args);
    }
}
