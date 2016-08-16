<?php

declare(strict_types=1);

namespace Amp;

use Interop\Async\{ Awaitable, Loop, Loop\Driver };

/**
 * Execute a callback within the event loop scope.
 * If an awaitable is returned, failure reasons are forwarded to the loop error callback.
 * Returned Generators are run as coroutines and handled the same as a returned awaitable.
 *
 * @see \Interop\Async\Loop::execute()
 *
 * @param callable $callback
 * @param \Interop\Async\Loop\Driver|null $driver
 */
function execute(callable $callback, Driver $driver = null) {
    Loop::execute(function () use ($callback) {
        $result = $callback();

        if ($result instanceof \Generator) {
            $result = new Coroutine($result);
        }

        if ($result instanceof Awaitable) {
            rethrow($result);
        }
    }, $driver);
}

/**
 * Stops the event loop.
 *
 * @see \Interop\Async\Loop::stop()
 */
function stop() {
    Loop::stop();
}

/**
 * Execute a callback when a stream resource becomes readable.
 * If an awaitable is returned, failure reasons are forwarded to the loop error callback.
 * Returned Generators are run as coroutines and handled the same as a returned awaitable.
 *
 * @see \Interop\Async\Loop::onReadable()
 *
 * @param resource $stream The stream to monitor.
 * @param callable(string $watcherId, resource $stream, mixed $data) $callback The callback to execute.
 * @param mixed $data
 *
 * @return string Watcher identifier.
 */
function onReadable($stream, callable $callback, $data = null): string {
    return Loop::onReadable($stream, function ($watcherId, $stream, $data) use ($callback) {
        $result = $callback($watcherId, $stream, $data);

        if ($result instanceof \Generator) {
            $result = new Coroutine($result);
        }

        if ($result instanceof Awaitable) {
            rethrow($result);
        }
    }, $data);
}

/**
 * Execute a callback when a stream resource becomes writable.
 * If an awaitable is returned, failure reasons are forwarded to the loop error callback.
 * Returned Generators are run as coroutines and handled the same as a returned awaitable.
 *
 * @see \Interop\Async\Loop::onWritable()
 *
 * @param resource $stream The stream to monitor.
 * @param callable(string $watcherId, resource $stream, mixed $data) $callback The callback to execute.
 * @param mixed $data
 *
 * @return string Watcher identifier.
 */
function onWritable($stream, callable $callback, $data = null): string {
    return Loop::onWritable($stream, function ($watcherId, $stream, $data) use ($callback) {
        $result = $callback($watcherId, $stream, $data);

        if ($result instanceof \Generator) {
            $result = new Coroutine($result);
        }

        if ($result instanceof Awaitable) {
            rethrow($result);
        }
    }, $data);
}

/**
 * Execute a callback when a signal is received.
 *
 * @see \Interop\Async\Loop::onSignal()
 *
 * @param int $signo The signal number to monitor.
 * @param callable(string $watcherId, int $signo, mixed $data) $callback The callback to execute.
 * @param mixed $data
 *
 * @return string Watcher identifier.
 */
function onSignal(int $signo, callable $callback, $data = null): string {
    return Loop::onSignal($signo, function ($watcherId, $signo, $data) use ($callback) {
        $result = $callback($watcherId, $signo, $data);

        if ($result instanceof \Generator) {
            $result = new Coroutine($result);
        }

        if ($result instanceof Awaitable) {
            rethrow($result);
        }
    }, $data);
}

/**
 * Defer the execution of a callback.
 * If an awaitable is returned, failure reasons are forwarded to the loop error callback.
 * Returned Generators are run as coroutines and handled the same as a returned awaitable.
 * 
 * @see \Interop\Async\Loop::defer()
 *
 * @param callable(string $watcherId, mixed $data) $callback The callback to delay.
 * @param mixed $data
 *
 * @return string Watcher identifier.
 */
function defer(callable $callback, $data = null): string {
    return Loop::defer(function ($watcherId, $data) use ($callback) {
        $result = $callback($watcherId, $data);

        if ($result instanceof \Generator) {
            $result = new Coroutine($result);
        }

        if ($result instanceof Awaitable) {
            rethrow($result);
        }
    }, $data);
}

/**
 * Delay the execution of a callback.
 * If an awaitable is returned, failure reasons are forwarded to the loop error callback.
 * Returned Generators are run as coroutines and handled the same as a returned awaitable.
 * 
 * @see \Interop\Async\Loop::delay()
 *
 * @param int $time
 * @param callable(string $watcherId, mixed $data) $callback The callback to delay.
 * @param mixed $data
 *
 * @return string Watcher identifier.
 */
function delay(int $time, callable $callback, $data = null): string {
    return Loop::delay($time, function ($watcherId, $data) use ($callback) {
        $result = $callback($watcherId, $data);

        if ($result instanceof \Generator) {
            $result = new Coroutine($result);
        }

        if ($result instanceof Awaitable) {
            rethrow($result);
        }
    }, $data);
}

/**
 * Repeatedly execute a callback.
 * If an awaitable is returned, failure reasons are forwarded to the loop error callback.
 * Returned Generators are run as coroutines and handled the same as a returned awaitable.
 * 
 * @see \Interop\Async\Loop::repeat()
 *
 * @param int $time
 * @param callable(string $watcherId, mixed $data) $callback The callback to delay.
 * @param mixed $data
 *
 * @return string Watcher identifier.
 */
function repeat(int $time, callable $callback, $data = null): string {
    return Loop::repeat($time, function ($watcherId, $data) use ($callback) {
        $result = $callback($watcherId, $data);

        if ($result instanceof \Generator) {
            $result = new Coroutine($result);
        }

        if ($result instanceof Awaitable) {
            rethrow($result);
        }
    }, $data);
}

/**
 * Enable a watcher.
 *
 * @see \Interop\Async\Loop::enable()
 *
 * @param string $watcherId
 */
function enable(string $watcherId) {
    Loop::enable($watcherId);
}

/**
 * Disable a watcher.
 *
 * @see \Interop\Async\Loop::disable()
 *
 * @param string $watcherId
 */
function disable(string $watcherId) {
    Loop::disable($watcherId);
}

/**
 * Cancel a watcher.
 *
 * @see \Interop\Async\Loop::cancel()
 *
 * @param string $watcherId
 */
function cancel(string $watcherId) {
    Loop::cancel($watcherId);
}

/**
 * Reference a watcher.
 *
 * @see \Interop\Async\Loop::reference()
 *
 * @param string $watcherId
 */
function reference(string $watcherId) {
    Loop::reference($watcherId);
}

/**
 * Unreference a watcher.
 *
 * @see \Interop\Async\Loop::unreference()
 *
 * @param string $watcherId
 */
function unreference(string $watcherId) {
    Loop::unreference($watcherId);
}

/**
 * @see \Interop\Async\Loop::setErrorHandler()
 *
 * @param callable $callback
 */
function setErrorHandler(callable $callback) {
    Loop::setErrorHandler(function ($exception) use ($callback) {
        $result = $callback($exception);

        if ($result instanceof \Generator) {
            $result = new Coroutine($result);
        }

        if ($result instanceof Awaitable) {
            rethrow($result);
        }
    });
}

/**
 * Wraps the callback in an awaitable/coroutine-aware function that automatically upgrades Generators to coroutines and
 * calls rethrow() on returned awaitables (including coroutines created from returned Generators).
 *
 * @param callable(...$args): \Generator|\Interop\Async\Awaitable|mixed $callback
 *
 * @return callable(...$args): void
 */
function wrap(callable $callback): callable {
    return function (...$args) use ($callback) {
        $result = $callback(...$args);

        if ($result instanceof \Generator) {
            $result = new Coroutine($result);
        }

        if ($result instanceof Awaitable) {
            rethrow($result);
        }
    };
}

/**
 * Returns a new function that wraps $worker in a awaitable/coroutine-aware function that automatically upgrades
 * Generators to coroutines. The returned function always returns an awaitable when invoked. If $worker throws, a failed
 * awaitable is returned.
 *
 * @param callable(mixed ...$args): mixed $worker
 *
 * @return callable(mixed ...$args): \Interop\Async\Awaitable
 */
function coroutine(callable $worker): callable {
    return function (...$args) use ($worker): Awaitable {
        try {
            $result = $worker(...$args);
        } catch (\Throwable $exception) {
            return new Failure($exception);
        }

        if ($result instanceof \Generator) {
            return new Coroutine($result);
        }
        
        if (!$result instanceof Awaitable) {
            return new Success($result);
        }

        return $result;
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
 * @throws \Throwable Awaitable failure reason.
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
        throw new \Error("Loop emptied without resolving awaitable");
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
function pipe(Awaitable $awaitable, callable $functor): Awaitable {
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
        }
    });

    return $deferred->getAwaitable();
}

/**
 * @param \Interop\Async\Awaitable $awaitable
 * @param string $className Exception class name to capture. Given callback will only be invoked if the failure reason
 *     is an instance of the given exception class name.
 * @param callable(\Throwable $exception): mixed $functor
 *
 * @return \Interop\Async\Awaitable
 */
function capture(Awaitable $awaitable, string $className, callable $functor): Awaitable {
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
        }
    });

    return $deferred->getAwaitable();
}

/**
 * Create an artificial timeout for any Awaitable.
 *
 * If the timeout expires before the awaitable is resolved, the returned awaitable fails with an instance of
 * \Amp\TimeoutException.
 *
 * @param \Interop\Async\Awaitable $awaitable
 * @param int $timeout Timeout in milliseconds.
 *
 * @return \Interop\Async\Awaitable
 */
function timeout(Awaitable $awaitable, int $timeout): Awaitable {
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
function lazy(callable $promisor, ...$args): Awaitable {
    if (empty($args)) {
        return new Internal\LazyAwaitable($promisor);
    }

    return new Internal\LazyAwaitable(function () use ($promisor, $args) {
        return $promisor(...$args);
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
 * @throws \Error If the provided object does not have a then() method.
 */
function adapt($thenable): Awaitable {
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
function lift(callable $worker): callable {
    /**
     * @param mixed ...$args Awaitables or values.
     *
     * @return \Interop\Async\Awaitable
     */
    return function (...$args) use ($worker): Awaitable {
        foreach ($args as $key => $arg) {
            if (!$arg instanceof Awaitable) {
                $args[$key] = new Success($arg);
            }
        }

        if (1 === \count($args)) {
            return pipe($args[0], $worker);
        }

        return pipe(all($args), function (array $args) use ($worker) {
            return $worker(...$args);
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
 * @throws \Error If a non-Awaitable is in the array.
 */
function any(array $awaitables): Awaitable {
    if (empty($awaitables)) {
        return new Success([[], []]);
    }

    $deferred = new Deferred;

    $pending = \count($awaitables);
    $errors = [];
    $values = [];

    foreach ($awaitables as $key => $awaitable) {
        if (!$awaitable instanceof Awaitable) {
            throw new \Error("Non-awaitable provided");
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
 * @throws \Error If a non-Awaitable is in the array.
 */
function all(array $awaitables): Awaitable {
    if (empty($awaitables)) {
        return new Success([]);
    }

    $deferred = new Deferred;

    $pending = \count($awaitables);
    $resolved = false;
    $values = [];

    foreach ($awaitables as $key => $awaitable) {
        if (!$awaitable instanceof Awaitable) {
            throw new \Error("Non-awaitable provided");
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
 * @throws \Error If the array is empty or a non-Awaitable is in the array.
 */
function first(array $awaitables): Awaitable {
    if (empty($awaitables)) {
        throw new \Error("No awaitables provided");
    }

    $deferred = new Deferred;

    $pending = \count($awaitables);
    $resolved = false;
    $exceptions = [];

    foreach ($awaitables as $key => $awaitable) {
        if (!$awaitable instanceof Awaitable) {
            throw new \Error("Non-awaitable provided");
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
function some(array $awaitables): Awaitable {
    if (empty($awaitables)) {
        throw new \Error("No awaitables provided");
    }

    $pending = \count($awaitables);

    $deferred = new Deferred;
    $values = [];
    $exceptions = [];

    foreach ($awaitables as $key => $awaitable) {
        if (!$awaitable instanceof Awaitable) {
            throw new \Error("Non-awaitable provided");
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
 * @throws \Error If the array is empty or a non-Awaitable is in the array.
 */
function choose(array $awaitables): Awaitable {
    if (empty($awaitables)) {
        throw new \Error("No awaitables provided");
    }

    $deferred = new Deferred;
    $resolved = false;

    foreach ($awaitables as $awaitable) {
        if (!$awaitable instanceof Awaitable) {
            throw new \Error("Non-awaitable provided");
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
function map(callable $callback, array ...$awaitables): Awaitable {
    $callback = lift($callback);

    foreach ($awaitables as $awaitableSet) {
        foreach ($awaitableSet as $awaitable) {
            if (!$awaitable instanceof Awaitable) {
                throw new \Error("Non-awaitable provided");
            }
        }
    }

    return array_map($callback, ...$awaitables);
}

/**
 * @param \Amp\Observable $observable
 * @param callable(mixed $value): mixed $onNext
 * @param callable(mixed $value): mixed|null $onComplete
 *
 * @return \Amp\Observable
 */
function each(Observable $observable, callable $onNext, callable $onComplete = null): Observable {
    return new Emitter(function (callable $emit) use ($observable, $onNext, $onComplete) {
        $observable->subscribe(function ($value) use ($emit, $onNext) {
            return $emit($onNext($value));
        });

        $result = yield $observable;

        if ($onComplete === null) {
            return $result;
        }

        return $onComplete($result);
    });
}

/**
 * @param \Amp\Observable $observable
 * @param callable(mixed $value): bool $filter
 *
 * @return \Amp\Observable
 */
function filter(Observable $observable, callable $filter): Observable {
    return new Emitter(function (callable $emit) use ($observable, $filter) {
        $observable->subscribe(function ($value) use ($emit, $filter) {
            if (!$filter($value)) {
                return null;
            }
            return $emit($value);
        });

        return yield $observable;
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
function merge(array $observables): Observable {
    foreach ($observables as $observable) {
        if (!$observable instanceof Observable) {
            throw new \Error("Non-observable provided");
        }
    }

    return new Emitter(function (callable $emit) use ($observables) {
        $subscriptions = [];

        foreach ($observables as $observable) {
            $subscriptions[] = $observable->subscribe($emit);
        }

        try {
            $result = yield all($observables);
        } finally {
            foreach ($subscriptions as $subscription) {
                $subscription->unsubscribe();
            }
        }

        return $result;
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
function stream(array $awaitables): Observable {
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
            throw new \Error("Non-awaitable provided");
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
function concat(array $observables): Observable {
    foreach ($observables as $observable) {
        if (!$observable instanceof Observable) {
            throw new \Error("Non-observable provided");
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
                }

                return yield $emit($value);
            }));
            $previous[] = $observable;
            $awaitable = all($previous);
        }

        try {
            $result = yield $awaitable;
        } finally {
            foreach ($subscriptions as $subscription) {
                $subscription->unsubscribe();
            }
        }

        return $result;
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
 *
 * @throws \Error If the number of times to emit is not a positive value.
 */
function interval(int $interval, int $count = PHP_INT_MAX): Observable {
    if (0 >= $count) {
        throw new \Error("The number of times to emit must be a positive value");
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
 *
 * @throws \Error If the step is 0 or not of the correct sign.
 */
function range(int $start, int $end, int $step = 1): Observable {
    if (0 === $step) {
        throw new \Error("Step must be a non-zero integer");
    }

    if ((($end - $start) ^ $step) < 0) {
        throw new \Error("Step is not of the correct sign");
    }

    return new Emitter(function (callable $emit) use ($start, $end, $step) {
        for ($i = $start; $i <= $end; $i += $step) {
            yield $emit($i);
        }
    });
}
