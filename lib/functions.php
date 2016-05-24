<?php

namespace Amp\Awaitable;

use Interop\Async\Awaitable;
use Interop\Async\Loop;
use Interop\Async\LoopDriver;

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
            throw new \LogicException("The callable did not return a Generator");
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
    $awaitable->when(function ($exception) {
        if ($exception) {
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
        $awaitable->when(function ($e, $v) use (&$value, &$exception) {
            Loop::stop();
            $exception = $e;
            $value = $v;
        });
    }, $driver ?: Loop::get());

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
    $deferred = new Deferred;

    $awaitable->when(function ($exception, $value) use ($deferred, $functor) {
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
    $deferred = new Deferred;

    $awaitable->when(function ($exception, $value) use ($deferred, $functor) {
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
    $deferred = new Deferred;

    $watcher = Loop::delay($timeout, function () use ($deferred) {
        $deferred->fail(new Exception\TimeoutException);
    });

    $onResolved = function () use ($awaitable, $deferred, $watcher) {
        Loop::cancel($watcher);
        $deferred->resolve($awaitable);
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
        return new Internal\LazyAwaitable($promisor);
    }

    return new Internal\LazyAwaitable(function () use ($promisor, $args) {
        return \call_user_func_array($promisor, $args);
    });
}

/**
 * Adapts any object with a then(callable $onFulfilled, callable $onRejected) method to a awaitable usable by
 * components depending on placeholders implementing Awaitable.
 *
 * @param object $thenable Object with a then() method.
 *
 * @return \Interop\Async\Awaitable Awaitable resolved by the $thenable object.
 *
 * @throws \InvalidArgumentException If the provided object does not have a then() method.
 */
function adapt($thenable) {
    if (!\is_object($thenable) || !\method_exists($thenable, "then")) {
        throw new \InvalidArgumentException("Must provide an object with a then() method");
    }

    $deferred = new Deferred;

    $thenable->then([$deferred, 'resolve'], [$deferred, 'fail']);

    return $deferred->getAwaitable();
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

        foreach ($args as $key => $arg) {
            if (!$arg instanceof Awaitable) {
                $args[$key] = new Success($arg);
            }
        }

        if (1 === \count($args)) {
            return pipe($args[0], $worker);
        }

        return pipe(all($args), function (array $args) use ($worker) {
            return \call_user_func_array($worker, $args);
        });
    };
}

/**
 * Returns a awaitable that is resolved when all awaitables are resolved. The returned awaitable will not fail.
 * Returned awaitable succeeds with an array of resolved awaitables, with keys identical and corresponding to the
 * original given array.
 *
 * @param Awaitable[] $awaitables
 *
 * @return \Interop\Async\Awaitable
 *
 * @throws \InvalidArgumentException If a non-Awaitable is in the array.
 */
function settle(array $awaitables) {
    if (empty($awaitables)) {
        return new Success([]);
    }

    $deferred = new Deferred;

    $pending = \count($awaitables);

    $onResolved = function () use (&$awaitables, &$pending, $deferred) {
        if (0 === --$pending) {
            $deferred->resolve($awaitables);
        }
    };

    foreach ($awaitables as &$awaitable) {
        if (!$awaitable instanceof Awaitable) {
            throw new \InvalidArgumentException("Non-awaitable provided");
        }

        $awaitable->when($onResolved);
    }

    return $deferred->getAwaitable();
}

/**
 * Returns a awaitable that succeeds when all awaitables succeed, and fails if any awaitable fails. Returned
 * awaitable succeeds with an array of values used to succeed each contained awaitable, with keys corresponding to
 * the array of awaitables.
 *
 * @param Awaitable[] $awaitables
 *
 * @return \Interop\Async\Awaitable
 *
 * @throws \InvalidArgumentException If a non-Awaitable is in the array.
 */
function all(array $awaitables) {
    if (empty($awaitables)) {
        return new Success([]);
    }

    $deferred = new Deferred;

    $pending = \count($awaitables);
    $resolved = false;
    $values = [];

    foreach ($awaitables as $key => $awaitable) {
        if (!$awaitable instanceof Awaitable) {
            throw new \InvalidArgumentException("Non-awaitable provided");
        }

        $onResolved = function ($exception, $value) use ($key, &$values, &$pending, &$resolved, $deferred) {
            if ($resolved) {
                return;
            }

            if ($exception) {
                $resolved = true;
                $deferred->fail($exception);
                return;
            }

            $values[$key] = $value;
            if (0 === --$pending) {
                $deferred->resolve($values);
            }
        };

        $awaitable->when($onResolved);
    }

    return $deferred->getAwaitable();
}

/**
 * Returns a awaitable that succeeds when the first awaitable succeeds, and fails only if all awaitables fail.
 *
 * @param Awaitable[] $awaitables
 *
 * @return \Interop\Async\Awaitable
 *
 * @throws \InvalidArgumentException If the array is empty or a non-Awaitable is in the array.
 */
function first(array $awaitables) {
    if (empty($awaitables)) {
        throw new \InvalidArgumentException("No awaitables provided");
    }

    $deferred = new Deferred;

    $pending = \count($awaitables);
    $resolved = false;
    $exceptions = [];

    foreach ($awaitables as $key => $awaitable) {
        if (!$awaitable instanceof Awaitable) {
            throw new \InvalidArgumentException("Non-awaitable provided");
        }

        $onResolved = function ($exception, $value) use (&$exceptions, &$pending, &$resolved, $key, $deferred) {
            if ($resolved) {
                return;
            }

            if (!$exception) {
                $resolved = true;
                $deferred->resolve($value);
                return;
            }

            $exceptions[$key] = $exception;
            if (0 === --$pending) {
                $deferred->fail(new Exception\MultiReasonException($exceptions));
            }
        };

        $awaitable->when($onResolved);
    }

    return $deferred->getAwaitable();
}

/**
 * Returns a awaitable that succeeds when $required number of awaitables succeed. The awaitable fails if $required
 * number of awaitables can no longer succeed.
 *
 * @param Awaitable[] $awaitables
 * @param int $required Number of awaitables that must succeed to succeed the returned awaitable.
 *
 * @return \Interop\Async\Awaitable
 */
function some(array $awaitables, $required) {
    $required = (int) $required;

    if (0 >= $required) {
        return new Success([]);
    }

    $pending = \count($awaitables);

    if ($required > $pending) {
        throw new \InvalidArgumentException("Too few awaitables provided");
    }

    $deferred = new Deferred;

    $required = \min($pending, $required);
    $resolved = false;
    $values = [];
    $exceptions = [];

    foreach ($awaitables as $key => $awaitable) {
        if (!$awaitable instanceof Awaitable) {
            throw new \InvalidArgumentException("Non-awaitable provided");
        }

        $onResolved = function ($exception, $value) use (
            &$key, &$values, &$exceptions, &$pending, &$resolved, &$required, $deferred
        ) {
            if ($resolved) {
                return;
            }

            if ($exception) {
                $exceptions[$key] = $exception;
                if ($required > --$pending) {
                    $resolved = true;
                    $deferred->fail(new Exception\MultiReasonException($exceptions));
                }
                return;
            }

            $values[$key] = $value;
            --$pending;
            if (0 === --$required) {
                $resolved = true;
                $deferred->resolve($values);
            }
        };

        $awaitable->when($onResolved);
    }

    return $deferred->getAwaitable();
}

/**
 * Returns a awaitable that succeeds or fails when the first awaitable succeeds or fails.
 *
 * @param Awaitable[] $awaitables
 *
 * @return \Interop\Async\Awaitable
 *
 * @throws \InvalidArgumentException If the array is empty or a non-Awaitable is in the array.
 */
function choose(array $awaitables) {
    if (empty($awaitables)) {
        throw new \InvalidArgumentException("No awaitables provided");
    }

    $deferred = new Deferred;
    $resolved = false;

    foreach ($awaitables as $awaitable) {
        if (!$awaitable instanceof Awaitable) {
            throw new \InvalidArgumentException("Non-awaitable provided");
        }

        $awaitable->when(function ($exception, $value) use (&$resolved, $deferred) {
            if ($resolved) {
                return;
            }

            $resolved = true;

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
 * @param Awaitable[] ...$awaitables
 *
 * @return \Interop\Async\Awaitable[] Array of awaitables resolved with the result of the mapped function.
 */
function map(callable $callback /* array ...$awaitables */) {
    $args = \func_get_args();
    $args[0] = lift($args[0]);

    $count = count($args);

    for ($i = 1; $i < $count; ++$i) {
        foreach ($args[$i] as $awaitable) {
            if (!$awaitable instanceof Awaitable) {
                throw new \InvalidArgumentException('Non-awaitable provided.');
            }
        }
    }

    return \call_user_func_array("array_map", $args);
}
