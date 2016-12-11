<?php declare(strict_types = 1);

namespace Amp;

use Interop\Async\{ Promise, Loop, Loop\Driver };

/**
 * Execute a callback within the event loop scope.
 * Returned Generators are run as coroutines. Failures of the coroutine are forwarded to the loop error handler.
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
            rethrow(new Coroutine($result));
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
 * Returned Generators are run as coroutines. Failures of the coroutine are forwarded to the loop error handler.
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
            rethrow(new Coroutine($result));
            return;
        }
    }, $data);
}

/**
 * Execute a callback when a stream resource becomes writable.
 * Returned Generators are run as coroutines. Failures of the coroutine are forwarded to the loop error handler.
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
            rethrow(new Coroutine($result));
        }
    }, $data);
}

/**
 * Execute a callback when a signal is received.
 * Returned Generators are run as coroutines. Failures of the coroutine are forwarded to the loop error handler.
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
            rethrow(new Coroutine($result));
        }
    }, $data);
}

/**
 * Defer the execution of a callback.
 * Returned Generators are run as coroutines. Failures of the coroutine are forwarded to the loop error handler.
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
            rethrow(new Coroutine($result));
        }
    }, $data);
}

/**
 * Delay the execution of a callback.
 * Returned Generators are run as coroutines. Failures of the coroutine are forwarded to the loop error handler.
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
            rethrow(new Coroutine($result));
        }
    }, $data);
}

/**
 * Repeatedly execute a callback.
 * Returned Generators are run as coroutines. Failures of the coroutine are forwarded to the loop error handler.
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
            rethrow(new Coroutine($result));
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
 * Returned Generators are run as coroutines. Failures of the coroutine are forwarded to the loop error handler.
 *
 * @see \Interop\Async\Loop::setErrorHandler()
 *
 * @param callable $callback
 */
function setErrorHandler(callable $callback) {
    Loop::setErrorHandler(function ($exception) use ($callback) {
        $result = $callback($exception);
        if ($result instanceof \Generator) {
            rethrow(new Coroutine($result));
        }
    });
}

/**
 * Wraps the callback in a promise/coroutine-aware function that automatically upgrades Generators to coroutines and
 * calls rethrow() on the created coroutine.
 *
 * @param callable(...$args): \Generator|\Interop\Async\Promise|mixed $callback
 *
 * @return callable(...$args): void
 */
function wrap(callable $callback): callable {
    return function (...$args) use ($callback) {
        $result = $callback(...$args);
        if ($result instanceof \Generator) {
            rethrow(new Coroutine($result));
        }
    };
}

/**
 * Returns a new function that wraps $worker in a promise/coroutine-aware function that automatically upgrades
 * Generators to coroutines. The returned function always returns a promise when invoked. If $worker throws, a failed
 * promise is returned.
 *
 * @param callable(mixed ...$args): mixed $worker
 *
 * @return callable(mixed ...$args): \Interop\Async\Promise
 */
function coroutine(callable $worker): callable {
    return function (...$args) use ($worker): Promise {
        try {
            $result = $worker(...$args);
        } catch (\Throwable $exception) {
            return new Failure($exception);
        }

        if ($result instanceof \Generator) {
            return new Coroutine($result);
        }
        
        if (!$result instanceof Promise) {
            return new Success($result);
        }

        return $result;
    };
}

/**
 * Registers a callback that will forward the failure reason to the Loop error handler if the promise fails.
 *
 * @param \Interop\Async\Promise $promise
 */
function rethrow(Promise $promise) {
    $promise->when(function ($exception) {
        if ($exception) {
            throw $exception;
        }
    });
}

/**
 * Runs the event loop until the promise is resolved. Should not be called within a running event loop.
 *
 * @param \Interop\Async\Promise $promise
 *
 * @return mixed Promise success value.
 *
 * @throws \Throwable Promise failure reason.
 */
function wait(Promise $promise) {
    $resolved = false;
    Loop::execute(function () use (&$resolved, &$value, &$exception, $promise) {
        $promise->when(function ($e, $v) use (&$resolved, &$value, &$exception) {
            Loop::stop();
            $resolved = true;
            $exception = $e;
            $value = $v;
        });
    }, Loop::get());

    if (!$resolved) {
        throw new \Error("Loop stopped without resolving promise");
    }

    if ($exception) {
        throw $exception;
    }

    return $value;
}

/**
 * Pipe the promised value through the specified functor once it resolves.
 *
 * @param \Interop\Async\Promise $promise
 * @param callable(mixed $value): mixed $functor
 *
 * @return \Interop\Async\Promise
 */
function pipe(Promise $promise, callable $functor): Promise {
    $deferred = new Deferred;

    $promise->when(function ($exception, $value) use ($deferred, $functor) {
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

    return $deferred->promise();
}

/**
 * @param \Interop\Async\Promise $promise
 * @param string $className Exception class name to capture. Given callback will only be invoked if the failure reason
 *     is an instance of the given exception class name.
 * @param callable(\Throwable $exception): mixed $functor
 *
 * @return \Interop\Async\Promise
 */
function capture(Promise $promise, string $className, callable $functor): Promise {
    $deferred = new Deferred;

    $promise->when(function ($exception, $value) use ($deferred, $className, $functor) {
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

    return $deferred->promise();
}

/**
 * Create an artificial timeout for any Promise.
 *
 * If the timeout expires before the promise is resolved, the returned promise fails with an instance of
 * \Amp\TimeoutException.
 *
 * @param \Interop\Async\Promise $promise
 * @param int $timeout Timeout in milliseconds.
 *
 * @return \Interop\Async\Promise
 */
function timeout(Promise $promise, int $timeout): Promise {
    $deferred = new Deferred;
    $resolved = false;

    $watcher = Loop::delay($timeout, function () use (&$resolved, $deferred) {
        if (!$resolved) {
            $resolved = true;
            $deferred->fail(new TimeoutException);
        }
    });

    $promise->when(function () use (&$resolved, $promise, $deferred, $watcher) {
        Loop::cancel($watcher);

        if ($resolved) {
            return;
        }

        $resolved = true;
        $deferred->resolve($promise);
    });

    return $deferred->promise();
}

/**
 * Returns a promise that calls $promisor only when the result of the promise is requested (i.e. when()  is called on
 * the returned promise). $promisor can return a promise or any value. If $promisor throws an exception, the returned
 * promise is rejected with that exception.
 *
 * @param callable $promisor
 * @param mixed ...$args
 *
 * @return \Interop\Async\Promise
 */
function lazy(callable $promisor, ...$args): Promise {
    if (empty($args)) {
        return new Internal\LazyPromise($promisor);
    }

    return new Internal\LazyPromise(function () use ($promisor, $args) {
        return $promisor(...$args);
    });
}

/**
 * Adapts any object with a then(callable $onFulfilled, callable $onRejected) method to a promise usable by
 * components depending on placeholders implementing Promise.
 *
 * @param object $thenable Object with a then() method.
 *
 * @return \Interop\Async\Promise Promise resolved by the $thenable object.
 *
 * @throws \Error If the provided object does not have a then() method.
 */
function adapt($thenable): Promise {
    $deferred = new Deferred;

    $thenable->then([$deferred, 'resolve'], [$deferred, 'fail']);

    return $deferred->promise();
}

/**
 * Wraps the given callable $worker in a promise aware function that has the same number of arguments as $worker,
 * but those arguments may be promises for the future argument value or just values. The returned function will
 * return a promise for the return value of $worker and will never throw. The $worker function will not be called
 * until each promise given as an argument is fulfilled. If any promise provided as an argument fails, the
 * promise returned by the returned function will be failed for the same reason. The promise succeeds with
 * the return value of $worker or failed if $worker throws.
 *
 * @param callable $worker
 *
 * @return callable
 */
function lift(callable $worker): callable {
    /**
     * @param mixed ...$args Promises or values.
     *
     * @return \Interop\Async\Promise
     */
    return function (...$args) use ($worker): Promise {
        foreach ($args as $key => $arg) {
            if (!$arg instanceof Promise) {
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
 * Returns a promise that is resolved when all promises are resolved. The returned promise will not fail.
 * Returned promise succeeds with a two-item array delineating successful and failed promise results,
 * with keys identical and corresponding to the original given array.
 *
 * This function is the same as some() with the notable exception that it will never fail even
 * if all promises in the array resolve unsuccessfully.
 *
 * @param Promise[] $promises
 *
 * @return \Interop\Async\Promise
 *
 * @throws \Error If a non-Promise is in the array.
 */
function any(array $promises): Promise {
    if (empty($promises)) {
        return new Success([[], []]);
    }

    $deferred = new Deferred;

    $pending = \count($promises);
    $errors = [];
    $values = [];

    foreach ($promises as $key => $promise) {
        if (!$promise instanceof Promise) {
            throw new \Error("Non-promise provided");
        }

        $promise->when(function ($error, $value) use (&$pending, &$errors, &$values, $key, $deferred) {
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
    return $deferred->promise();
}

/**
 * Returns a promise that succeeds when all promises succeed, and fails if any promise fails. Returned
 * promise succeeds with an array of values used to succeed each contained promise, with keys corresponding to
 * the array of promises.
 *
 * @param Promise[] $promises
 *
 * @return \Interop\Async\Promise
 *
 * @throws \Error If a non-Promise is in the array.
 */
function all(array $promises): Promise {
    if (empty($promises)) {
        return new Success([]);
    }

    $deferred = new Deferred;

    $pending = \count($promises);
    $resolved = false;
    $values = [];

    foreach ($promises as $key => $promise) {
        if (!$promise instanceof Promise) {
            throw new \Error("Non-promise provided");
        }

        $promise->when(function ($exception, $value) use (&$values, &$pending, &$resolved, $key, $deferred) {
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

    return $deferred->promise();
}

/**
 * Returns a promise that succeeds when the first promise succeeds, and fails only if all promises fail.
 *
 * @param Promise[] $promises
 *
 * @return \Interop\Async\Promise
 *
 * @throws \Error If the array is empty or a non-Promise is in the array.
 */
function first(array $promises): Promise {
    if (empty($promises)) {
        throw new \Error("No promises provided");
    }

    $deferred = new Deferred;

    $pending = \count($promises);
    $resolved = false;
    $exceptions = [];

    foreach ($promises as $key => $promise) {
        if (!$promise instanceof Promise) {
            throw new \Error("Non-promise provided");
        }

        $promise->when(function ($exception, $value) use (&$exceptions, &$pending, &$resolved, $key, $deferred) {
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

    return $deferred->promise();
}

/**
 * Resolves with a two-item array delineating successful and failed Promise results.
 *
 * The returned promise will only fail if ALL of the promises fail.

 * @param Promise[] $promises
 *
 * @return \Interop\Async\Promise
 */
function some(array $promises): Promise {
    if (empty($promises)) {
        throw new \Error("No promises provided");
    }

    $pending = \count($promises);

    $deferred = new Deferred;
    $values = [];
    $exceptions = [];

    foreach ($promises as $key => $promise) {
        if (!$promise instanceof Promise) {
            throw new \Error("Non-promise provided");
        }

        $promise->when(function ($exception, $value) use (&$values, &$exceptions, &$pending, $key, $deferred) {
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

    return $deferred->promise();
}

/**
 * Returns a promise that succeeds or fails when the first promise succeeds or fails.
 *
 * @param Promise[] $promises
 *
 * @return \Interop\Async\Promise
 *
 * @throws \Error If the array is empty or a non-Promise is in the array.
 */
function choose(array $promises): Promise {
    if (empty($promises)) {
        throw new \Error("No promises provided");
    }

    $deferred = new Deferred;
    $resolved = false;

    foreach ($promises as $promise) {
        if (!$promise instanceof Promise) {
            throw new \Error("Non-promise provided");
        }

        $promise->when(function ($exception, $value) use (&$resolved, $deferred) {
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

    return $deferred->promise();
}

/**
 * Maps the callback to each promise as it succeeds. Returns an array of promises resolved by the return
 * callback value of the callback function. The callback may return promises or throw exceptions to fail
 * promises in the array. If a promise in the passed array fails, the callback will not be called and the
 * promise in the array fails for the same reason. Tip: Use all() or any() to determine when all
 * promises in the array have been resolved.
 *
 * @param callable(mixed $value): mixed $callback
 * @param Promise[] ...$promises
 *
 * @return \Interop\Async\Promise[] Array of promises resolved with the result of the mapped function.
 */
function map(callable $callback, array ...$promises): array {
    $callback = lift($callback);

    foreach ($promises as $promiseSet) {
        foreach ($promiseSet as $promise) {
            if (!$promise instanceof Promise) {
                throw new \Error("Non-promise provided");
            }
        }
    }

    return array_map($callback, ...$promises);
}

/**
 * @param \Amp\Observable $observable
 * @param callable(mixed $value): mixed $onNext
 * @param callable(mixed $value): mixed|null $onComplete
 *
 * @return \Amp\Observable
 */
function each(Observable $observable, callable $onNext, callable $onComplete = null): Observable {
    $postponed = new Postponed;
    $pending = true;
    
    $observable->subscribe(function ($value) use (&$pending, $postponed, $onNext) {
        if ($pending) {
            try {
                return $postponed->emit($onNext($value));
            } catch (\Throwable $exception) {
                $pending = false;
                $postponed->fail($exception);
            }
        }
        return null;
    });
    
    $observable->when(function ($exception, $value) use (&$pending, $postponed, $onComplete) {
        if (!$pending) {
            return;
        }
        $pending = false;
        
        if ($exception) {
            $postponed->fail($exception);
            return;
        }
        
        if ($onComplete === null) {
            $postponed->resolve($value);
            return;
        }
        
        try {
            $postponed->resolve($onComplete($value));
        } catch (\Throwable $exception) {
            $postponed->fail($exception);
        }
    });
    
    return $postponed->observe();
}

/**
 * @param \Amp\Observable $observable
 * @param callable(mixed $value): bool $filter
 *
 * @return \Amp\Observable
 */
function filter(Observable $observable, callable $filter): Observable {
    $postponed = new Postponed;
    $pending = true;
    
    $observable->subscribe(function ($value) use (&$pending, $postponed, $filter) {
        if ($pending) {
            try {
                if (!$filter($value)) {
                    return null;
                }
                return $postponed->emit($value);
            } catch (\Throwable $exception) {
                $pending = false;
                $postponed->fail($exception);
            }
        }
        return null;
    });
    
    $observable->when(function ($exception, $value) use (&$pending, $postponed) {
        if (!$pending) {
            return;
        }
        $pending = false;
        
        if ($exception) {
            $postponed->fail($exception);
            return;
        }
        
        $postponed->resolve($value);
    });
    
    return $postponed->observe();
}

/**
 * Creates an observable that emits values emitted from any observable in the array of observables.
 *
 * @param \Amp\Observable[] $observables
 *
 * @return \Amp\Observable
 */
function merge(array $observables): Observable {
    $postponed = new Postponed;
    
    foreach ($observables as $observable) {
        if (!$observable instanceof Observable) {
            throw new \Error("Non-observable provided");
        }
        $observable->subscribe([$postponed, 'emit']);
    }
    
    all($observables)->when(function ($exception, array $values) use ($postponed) {
        if ($exception) {
            $postponed->fail($exception);
            return;
        }
    
        $postponed->resolve($values);
    });
    
    return $postponed->observe();
}


/**
 * Creates an observable from the given array of promises, emitting the success value of each provided promise or
 * failing if any promise fails.
 *
 * @param \Interop\Async\Promise[] $promises
 *
 * @return \Amp\Observable
 *
 * @throws \Error If a non-promise is provided.
 */
function stream(array $promises): Observable {
    foreach ($promises as $promise) {
        if (!$promise instanceof Promise) {
            throw new \Error("Non-promise provided");
        }
    }
    
    return new Emitter(function (callable $emit) use ($promises) {
        $emits = [];
        foreach ($promises as $promise) {
            $emits[] = $emit($promise);
        }
        yield all($emits);
    });
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
    
    $postponed = new Postponed;
    $subscriptions = [];
    $previous = [];
    $promise = all($previous);
    
    foreach ($observables as $observable) {
        $subscriptions[] = $observable->subscribe(coroutine(function ($value) use ($postponed, $promise) {
            try {
                yield $promise;
            } catch (\Throwable $exception) {
                // Ignore exception in this context.
            }
            
            return yield $postponed->emit($value);
        }));
        $previous[] = $observable;
        $promise = all($previous);
    }
    
    $promise->when(function ($exception, array $values) use ($postponed) {
        if ($exception) {
            $postponed->fail($exception);
            return;
        }
        
        $postponed->resolve($values);
    });
    
    return $postponed->observe();
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

    return $postponed->observe();
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
