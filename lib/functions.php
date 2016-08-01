<?php

namespace Amp;

use Interop\Async\Awaitable;
use Interop\Async\Loop;

/**
 * Returns a new function that when invoked runs the Generator returned by $worker as a coroutine.
 *
 * @param callable(mixed ...$args): \Generator $worker
 *
 * @return callable(mixed ...$args): \Amp\Coroutine
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
 *
 * @return mixed Awaitable success value.
 *
 * @throws \Throwable|\Exception Awaitable failure reason.
 */
function wait(Awaitable $awaitable) {
    $resolved = false;
    Loop::execute(function () use (&$resolved, &$value, &$exception, $awaitable) {
        $awaitable->when(function ($e, $v) use (&$resolved, &$value, &$exception) {
            Loop::stop();
            $resolved = true;
            $exception = $e;
            $value = $v;
        });
    }, Loop::get());

    if (!$resolved) {
        throw new \LogicException("Loop emptied without resolving awaitable");
    }

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
 * @param string $className Exception class name to capture. Given callback will only be invoked if the failure reason
 *     is an instance of the given exception class name.
 * @param callable(\Throwable|\Exception $exception): mixed $functor
 *
 * @return \Interop\Async\Awaitable
 */
function capture(Awaitable $awaitable, $className, callable $functor) {
    $deferred = new Deferred;

    $awaitable->when(function ($exception, $value) use ($deferred, $className, $functor) {
        if (!$exception) {
            $deferred->resolve($value);
            return;
        }

        if (!$exception instanceof $className) {
            $deferred->fail($exception);
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
 * \Amp\Exception\TimeoutException.
 *
 * @param \Interop\Async\Awaitable $awaitable
 * @param int $timeout Timeout in milliseconds.
 *
 * @return \Interop\Async\Awaitable
 */
function timeout(Awaitable $awaitable, $timeout) {
    $deferred = new Deferred;
    $resolved = false;

    $watcher = Loop::delay($timeout, function () use (&$resolved, $deferred) {
        if (!$resolved) {
            $resolved = true;
            $deferred->fail(new TimeoutException);
        }
    });

    $awaitable->when(function () use (&$resolved, $awaitable, $deferred, $watcher) {
        Loop::cancel($watcher);

        if ($resolved) {
            return;
        }

        $resolved = true;
        $deferred->resolve($awaitable);
    });

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
 * Returned awaitable succeeds with a two-item array delineating successful and failed awaitable results,
 * with keys identical and corresponding to the original given array.
 *
 * This function is the same as some() with the notable exception that it will never fail even
 * if all awaitables in the array resolve unsuccessfully.
 *
 * @param Awaitable[] $awaitables
 *
 * @return \Interop\Async\Awaitable
 *
 * @throws \InvalidArgumentException If a non-Awaitable is in the array.
 */
function any(array $awaitables) {
    if (empty($awaitables)) {
        return new Success([[], []]);
    }

    $deferred = new Deferred;

    $pending = \count($awaitables);
    $errors = [];
    $values = [];

    foreach ($awaitables as $key => $awaitable) {
        if (!$awaitable instanceof Awaitable) {
            throw new \InvalidArgumentException("Non-awaitable provided");
        }

        $awaitable->when(function ($error, $value) use (&$pending, &$errors, &$values, $key, $deferred) {
            if ($error) {
                $errors[$key] = $error;
            } else {
                $values[$key] = $value;
            }

            if (--$pending === 0) {
                $deferred->resolve([$errors, $values]);
            }
        });
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

        $awaitable->when(function ($exception, $value) use (&$values, &$pending, &$resolved, $key, $deferred) {
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
        });
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

        $awaitable->when(function ($exception, $value) use (&$exceptions, &$pending, &$resolved, $key, $deferred) {
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
                $deferred->fail(new MultiReasonException($exceptions));
            }
        });
    }

    return $deferred->getAwaitable();
}

/**
 * Resolves with a two-item array delineating successful and failed Awaitable results.
 *
 * The returned awaitable will only fail if ALL of the awaitables fail.

 * @param Awaitable[] $awaitables
 *
 * @return \Interop\Async\Awaitable
 */
function some(array $awaitables) {
    if (empty($awaitables)) {
        throw new \InvalidArgumentException("No awaitables provided");
    }

    $pending = \count($awaitables);

    $deferred = new Deferred;
    $values = [];
    $exceptions = [];

    foreach ($awaitables as $key => $awaitable) {
        if (!$awaitable instanceof Awaitable) {
            throw new \InvalidArgumentException("Non-awaitable provided");
        }

        $awaitable->when(function ($exception, $value) use (&$values, &$exceptions, &$pending, $key, $deferred) {
            if ($exception) {
                $exceptions[$key] = $exception;
            } else {
                $values[$key] = $value;
            }

            if (0 === --$pending) {
                if (empty($values)) {
                    $deferred->fail(new MultiReasonException($exceptions));
                    return;
                }

                $deferred->resolve([$exceptions, $values]);
            }
        });
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
 * awaitable in the array fails for the same reason. Tip: Use all() or any() to determine when all
 * awaitables in the array have been resolved.
 *
 * @param callable(mixed $value): mixed $callback
 * @param Awaitable[] ...$awaitables
 *
 * @return \Interop\Async\Awaitable[] Array of awaitables resolved with the result of the mapped function.
 */
function map(callable $callback /* array ...$awaitables */) {
    $args = \func_get_args();
    $count = \count($args);
    $args[0] = lift($callback);

    for ($i = 1; $i < $count; ++$i) {
        foreach ($args[$i] as $awaitable) {
            if (!$awaitable instanceof Awaitable) {
                throw new \InvalidArgumentException('Non-awaitable provided');
            }
        }
    }

    return \call_user_func_array("array_map", $args);
}

/**
 * @param \Amp\Observable $observable
 * @param callable(mixed $value): mixed $onNext
 * @param callable(mixed $value): mixed|null $onComplete
 *
 * @return \Amp\Observable
 */
function each(Observable $observable, callable $onNext, callable $onComplete = null) {
    return new Emitter(function (callable $emit) use ($observable, $onNext, $onComplete) {
        $observable->subscribe(function ($value) use ($emit, $onNext) {
            return $emit($onNext($value));
        });

        $result = (yield $observable);

        if ($onComplete === null) {
            yield Coroutine::result($result);
            return;
        }

        yield Coroutine::result($onComplete($result));
    });
}

/**
 * @param \Amp\Observable $observable
 * @param callable(mixed $value): bool $filter
 *
 * @return \Amp\Observable
 */
function filter(Observable $observable, callable $filter) {
    return new Emitter(function (callable $emit) use ($observable, $filter) {
        $observable->subscribe(function ($value) use ($emit, $filter) {
            if (!$filter($value)) {
                return null;
            }
            return $emit($value);
        });

        yield Coroutine::result(yield $observable);
    });
}

/**
 * Creates an observable that emits values emitted from any observable in the array of observables. Values in the
 * array are passed through the from() function, so they may be observables, arrays of values to emit, awaitables,
 * or any other value.
 *
 * @param \Amp\Observable[] $observables
 *
 * @return \Amp\Observable
 */
function merge(array $observables) {
    foreach ($observables as $observable) {
        if (!$observable instanceof Observable) {
            throw new \InvalidArgumentException("Non-observable provided");
        }
    }

    return new Emitter(function (callable $emit) use ($observables) {
        $subscriptions = [];

        foreach ($observables as $observable) {
            $subscriptions[] = $observable->subscribe($emit);
        }

        try {
            $result = (yield all($observables));
        } finally {
            foreach ($subscriptions as $subscription) {
                $subscription->unsubscribe();
            }
        }

        yield Coroutine::result($result);
    });
}


/**
 * Creates an observable from the given array of awaitables, emitting the success value of each provided awaitable or
 * failing if any awaitable fails.
 *
 * @param \Interop\Async\Awaitable[] $awaitables
 *
 * @return \Amp\Observable
 */
function stream(array $awaitables) {
    $postponed = new Postponed;

    if (empty($awaitables)) {
        $postponed->resolve();
        return $postponed->getObservable();
    }

    $pending = \count($awaitables);
    $onResolved = function ($exception, $value) use (&$pending, $postponed) {
        if ($pending <= 0) {
            return;
        }

        if ($exception) {
            $pending = 0;
            $postponed->fail($exception);
            return;
        }

        $postponed->emit($value);

        if (--$pending === 0) {
            $postponed->complete();
        }
    };

    foreach ($awaitables as $awaitable) {
        if (!$awaitable instanceof Awaitable) {
            throw new \InvalidArgumentException("Non-awaitable provided");
        }

        $awaitable->when($onResolved);
    }

    return $postponed->getObservable();
}

/**
 * Concatenates the given observables into a single observable, emitting values from a single observable at a time. The
 * prior observable must complete before values are emitted from any subsequent observable. Observables are concatenated
 * in the order given (iteration order of the array).
 *
 * @param array $observables
 *
 * @return \Amp\Observable
 */
function concat(array $observables) {
    foreach ($observables as $observable) {
        if (!$observable instanceof Observable) {
            throw new \InvalidArgumentException("Non-observable provided");
        }
    }

    return new Emitter(function (callable $emit) use ($observables) {
        $subscriptions = [];
        $previous = [];
        $awaitable = all($previous);

        foreach ($observables as $observable) {
            $subscriptions[] = $observable->subscribe(coroutine(function ($value) use ($emit, $awaitable) {
                try {
                    yield $awaitable;
                } catch (\Throwable $exception) {
                    // Ignore exception in this context.
                } catch (\Exception $exception) {
                    // Ignore exception in this context.
                }

                yield Coroutine::result(yield $emit($value));
            }));
            $previous[] = $observable;
            $awaitable = all($previous);
        }

        try {
            $result = (yield $awaitable);
        } finally {
            foreach ($subscriptions as $subscription) {
                $subscription->unsubscribe();
            }
        }

        yield Coroutine::result($result);
    });
}

/**
 * Returns an observable that emits a value every $interval milliseconds after (up to $count times). The value emitted
 * is an integer of the number of times the observable emitted a value.
 *
 * @param int $interval Time interval between emitted values in milliseconds.
 * @param int $count Number of values to emit. PHP_INT_MAX by default.
 *
 * @return \Amp\Observable
 */
function interval($interval, $count = PHP_INT_MAX) {
    $count = (int) $count;
    if (0 >= $count) {
        throw new \InvalidArgumentException("The number of times to emit must be a positive value");
    }

    $postponed = new Postponed;

    Loop::repeat($interval, function ($watcher) use (&$i, $postponed, $count) {
        $postponed->emit(++$i);

        if ($i === $count) {
            Loop::cancel($watcher);
            $postponed->resolve();
        }
    });

    return $postponed->getObservable();
}

/**
 * @param int $start
 * @param int $end
 * @param int $step
 *
 * @return \Amp\Observable
 */
function range($start, $end, $step = 1) {
    $start = (int) $start;
    $end = (int) $end;
    $step = (int) $step;

    if (0 === $step) {
        throw new \InvalidArgumentException("Step must be a non-zero integer");
    }

    if ((($end - $start) ^ $step) < 0) {
        throw new \InvalidArgumentException("Step is not of the correct sign");
    }

    return new Emitter(function (callable $emit) use ($start, $end, $step) {
        for ($i = $start; $i <= $end; $i += $step) {
            yield $emit($i);
        }
    });
}
