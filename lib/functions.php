<?php

namespace Amp {
    use React\Promise\PromiseInterface as ReactPromise;

    /**
     * Returns a new function that wraps $callback in a promise/coroutine-aware function that automatically runs
     * Generators as coroutines. The returned function always returns a promise when invoked. Errors have to be handled
     * by the callback caller or they will go unnoticed.
     *
     * Use this function to create a coroutine-aware callable for a promise-aware callback caller.
     *
     * @param callable(mixed ...$args): mixed $callback
     *
     * @return callable(mixed ...$args): \Amp\Promise
     *
     * @see asyncCoroutine()
     */
    function coroutine(callable $callback): callable {
        return function (...$args) use ($callback): Promise {
            return call($callback, ...$args);
        };
    }

    /**
     * Returns a new function that wraps $callback in a promise/coroutine-aware function that automatically runs
     * Generators as coroutines. The returned function always returns void when invoked. Errors are forwarded to the
     * loop's error handler using `Amp\Promise\rethrow()`.
     *
     * Use this function to create a coroutine-aware callable for a non-promise-aware callback caller.
     *
     * @param callable(...$args): \Generator|\Amp\Promise|mixed $callback
     *
     * @return callable(...$args): void
     *
     * @see coroutine()
     */
    function asyncCoroutine(callable $callback): callable {
        return function (...$args) use ($callback) {
            Promise\rethrow(call($callback, ...$args));
        };
    }

    /**
     * Calls the given function, always returning a promise. If the function returns a Generator, it will be run as a
     * coroutine. If the function throws, a failed promise will be returned.
     *
     * @param callable(mixed ...$args): mixed $callback
     * @param array ...$args Arguments to pass to the function.
     *
     * @return \Amp\Promise
     */
    function call(callable $callback, ...$args): Promise {
        try {
            $result = $callback(...$args);
        } catch (\Throwable $exception) {
            return new Failure($exception);
        }

        if ($result instanceof \Generator) {
            return new Coroutine($result);
        }

        if ($result instanceof Promise) {
            return $result;
        }

        if ($result instanceof ReactPromise) {
            return Promise\adapt($result);
        }

        return new Success($result);
    }

    /**
     * Calls the given function. If the function returns a Generator, it will be run as a coroutine. If the function
     * throws or returns a failing promise, the failure is forwarded to the loop error handler.
     *
     * @param callable $callback
     * @param array    ...$args
     *
     * @throws \TypeError
     */
    function asyncCall(callable $callback, ...$args) {
        Promise\rethrow(call($callback, ...$args));
    }
}

namespace Amp\Promise {
    use Amp\Deferred;
    use Amp\Loop;
    use Amp\MultiReasonException;
    use Amp\Promise;
    use Amp\Success;
    use Amp\TimeoutException;
    use React\Promise\PromiseInterface as ReactPromise;
    use function Amp\Internal\createTypeError;

    /**
     * Registers a callback that will forward the failure reason to the event loop's error handler if the promise fails.
     *
     * Use this function if you neither return the promise nor handle a possible error yourself to prevent errors from
     * going entirely unnoticed.
     *
     * @param \Amp\Promise|\React\Promise\PromiseInterface $promise Promise to register the handler on.
     *
     * @throws \TypeError If $promise is not an instance of \Amp\Promise or \React\Promise\PromiseInterface.
     */
    function rethrow($promise) {
        if (!$promise instanceof Promise) {
            if ($promise instanceof ReactPromise) {
                $promise = adapt($promise);
            } else {
                throw createTypeError([Promise::class, ReactPromise::class], $promise);
            }
        }

        $promise->onResolve(function ($exception) {
            if ($exception) {
                throw $exception;
            }
        });
    }

    /**
     * Runs the event loop until the promise is resolved. Should not be called within a running event loop.
     *
     * Use this function only in synchronous contexts to wait for an asynchronous operation. Use coroutines and yield to
     * await promise resolution in a fully asynchronous application instead.
     *
     * @param \Amp\Promise|\React\Promise\PromiseInterface $promise Promise to wait for.
     *
     * @return mixed Promise success value.
     *
     * @throws \TypeError If $promise is not an instance of \Amp\Promise or \React\Promise\PromiseInterface.
     * @throws \Error If the event loop stopped without the $promise being resolved.
     * @throws \Throwable Promise failure reason.
     */
    function wait($promise) {
        if (!$promise instanceof Promise) {
            if ($promise instanceof ReactPromise) {
                $promise = adapt($promise);
            } else {
                throw createTypeError([Promise::class, ReactPromise::class], $promise);
            }
        }

        $resolved = false;

        try {
            Loop::run(function () use (&$resolved, &$value, &$exception, $promise) {
                $promise->onResolve(function ($e, $v) use (&$resolved, &$value, &$exception) {
                    Loop::stop();
                    $resolved = true;
                    $exception = $e;
                    $value = $v;
                });
            });
        } catch (\Throwable $throwable) {
            throw new \Error("Loop exceptionally stopped without resolving the promise", 0, $throwable);
        }

        if (!$resolved) {
            throw new \Error("Loop stopped without resolving the promise");
        }

        if ($exception) {
            throw $exception;
        }

        return $value;
    }

    /**
     * Creates an artificial timeout for any `Promise`.
     *
     * If the timeout expires before the promise is resolved, the returned promise fails with an instance of
     * `Amp\TimeoutException`.
     *
     * @param \Amp\Promise|\React\Promise\PromiseInterface $promise Promise to which the timeout is applied.
     * @param int $timeout Timeout in milliseconds.
     *
     * @return \Amp\Promise
     *
     * @throws \TypeError If $promise is not an instance of \Amp\Promise or \React\Promise\PromiseInterface.
     */
    function timeout($promise, int $timeout): Promise {
        if (!$promise instanceof Promise) {
            if ($promise instanceof ReactPromise) {
                $promise = adapt($promise);
            } else {
                throw createTypeError([Promise::class, ReactPromise::class], $promise);
            }
        }

        $deferred = new Deferred;

        $watcher = Loop::delay($timeout, function () use (&$deferred) {
            $temp = $deferred; // prevent double resolve
            $deferred = null;
            $temp->fail(new TimeoutException);
        });
        Loop::unreference($watcher);

        $promise->onResolve(function () use (&$deferred, $promise, $watcher) {
            if ($deferred !== null) {
                Loop::cancel($watcher);
                $deferred->resolve($promise);
            }
        });

        return $deferred->promise();
    }

    /**
     * Adapts any object with a done(callable $onFulfilled, callable $onRejected) or then(callable $onFulfilled,
     * callable $onRejected) method to a promise usable by components depending on placeholders implementing
     * \AsyncInterop\Promise.
     *
     * @param object $promise Object with a done() or then() method.
     *
     * @return \Amp\Promise Promise resolved by the $thenable object.
     *
     * @throws \Error If the provided object does not have a then() method.
     */
    function adapt($promise): Promise {
        $deferred = new Deferred;

        if (\method_exists($promise, 'done')) {
            $promise->done([$deferred, 'resolve'], [$deferred, 'fail']);
        } elseif (\method_exists($promise, 'then')) {
            $promise->then([$deferred, 'resolve'], [$deferred, 'fail']);
        } else {
            throw new \Error("Object must have a 'then' or 'done' method");
        }

        return $deferred->promise();
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
     * @return \Amp\Promise
     *
     * @throws \Error If a non-Promise is in the array.
     */
    function any(array $promises): Promise {
        return some($promises, 0);
    }

    /**
     * Returns a promise that succeeds when all promises succeed, and fails if any promise fails. Returned
     * promise succeeds with an array of values used to succeed each contained promise, with keys corresponding to
     * the array of promises.
     *
     * @param \Amp\Promise[] $promises Array of only promises.
     *
     * @return \Amp\Promise
     *
     * @throws \Error If a non-Promise is in the array.
     */
    function all(array $promises): Promise {
        if (empty($promises)) {
            return new Success([]);
        }

        $deferred = new Deferred;
        $result = $deferred->promise();

        $pending = \count($promises);
        $values = [];

        foreach ($promises as $key => $promise) {
            if ($promise instanceof ReactPromise) {
                $promise = adapt($promise);
            } elseif (!$promise instanceof Promise) {
                throw createTypeError([Promise::class, ReactPromise::class], $promise);
            }

            $values[$key] = null; // add entry to array to preserve order
            $promise->onResolve(function ($exception, $value) use (&$deferred, &$values, &$pending, $key) {
                if ($pending === 0) {
                    return;
                }

                if ($exception) {
                    $pending = 0;
                    $deferred->fail($exception);
                    $deferred = null;
                    return;
                }

                $values[$key] = $value;
                if (0 === --$pending) {
                    $deferred->resolve($values);
                }
            });
        }

        return $result;
    }

    /**
     * Returns a promise that succeeds when the first promise succeeds, and fails only if all promises fail.
     *
     * @param \Amp\Promise[] $promises Array of only promises.
     *
     * @return \Amp\Promise
     *
     * @throws \Error If the array is empty or a non-Promise is in the array.
     */
    function first(array $promises): Promise {
        if (empty($promises)) {
            throw new \Error("No promises provided");
        }

        $deferred = new Deferred;
        $result = $deferred->promise();

        $pending = \count($promises);
        $exceptions = [];

        foreach ($promises as $key => $promise) {
            if ($promise instanceof ReactPromise) {
                $promise = adapt($promise);
            } elseif (!$promise instanceof Promise) {
                throw createTypeError([Promise::class, ReactPromise::class], $promise);
            }

            $exceptions[$key] = null; // add entry to array to preserve order
            $promise->onResolve(function ($error, $value) use (&$deferred, &$exceptions, &$pending, $key) {
                if ($pending === 0) {
                    return;
                }

                if (!$error) {
                    $pending = 0;
                    $deferred->resolve($value);
                    $deferred = null;
                    return;
                }

                $exceptions[$key] = $error;
                if (0 === --$pending) {
                    $deferred->fail(new MultiReasonException($exceptions));
                }
            });
        }

        return $result;
    }

    /**
     * Resolves with a two-item array delineating successful and failed Promise results.
     *
     * The returned promise will only fail if the given number of required promises fail.
     *
     * @param \Amp\Promise[] $promises Array of only promises.
     * @param int $required Number of promises that must succeed for the returned promise to succeed.
     *
     * @return \Amp\Promise
     *
     * @throws \Error If a non-Promise is in the array.
     */
    function some(array $promises, int $required = 1): Promise {
        if ($required < 0) {
            throw new \Error("Number of promises required must be non-negative");
        }

        $pending = \count($promises);

        if ($required > $pending) {
            throw new \Error("Too few promises provided");
        }

        if (empty($promises)) {
            return new Success([[], []]);
        }

        $deferred = new Deferred;
        $result = $deferred->promise();
        $values = [];
        $exceptions = [];

        foreach ($promises as $key => $promise) {
            if ($promise instanceof ReactPromise) {
                $promise = adapt($promise);
            } elseif (!$promise instanceof Promise) {
                throw createTypeError([Promise::class, ReactPromise::class], $promise);
            }

            $values[$key] = $exceptions[$key] = null; // add entry to arrays to preserve order
            $promise->onResolve(function ($exception, $value) use (
                &$values, &$exceptions, &$pending, $key, $required, $deferred
            ) {
                if ($exception) {
                    $exceptions[$key] = $exception;
                    unset($values[$key]);
                } else {
                    $values[$key] = $value;
                    unset($exceptions[$key]);
                }

                if (0 === --$pending) {
                    if (\count($values) < $required) {
                        $deferred->fail(new MultiReasonException($exceptions));
                    } else {
                        $deferred->resolve([$exceptions, $values]);
                    }
                }
            });
        }

        return $result;
    }
}

namespace Amp\Iterator {
    use Amp\Delayed;
    use Amp\Emitter;
    use Amp\Iterator;
    use Amp\Producer;
    use Amp\Promise;
    use function Amp\coroutine;
    use function Amp\Internal\createTypeError;

    /**
     * Creates an iterator from the given iterable, emitting the each value. The iterable may contain promises. If any
     * promise fails, the iterator will fail with the same reason.
     *
     * @param array|\Traversable $iterable Elements to emit.
     * @param int $delay Delay between element emissions in milliseconds.
     *
     * @return \Amp\Iterator
     *
     * @throws \TypeError If the argument is not an array or instance of \Traversable.
     */
    function fromIterable(/* iterable */ $iterable, int $delay = 0): Iterator {
        if (!$iterable instanceof \Traversable && !\is_array($iterable)) {
            throw createTypeError(["array", "Traversable"], $iterable);
        }

        return new Producer(function (callable $emit) use ($iterable, $delay) {
            foreach ($iterable as $value) {
                if ($delay) {
                    yield new Delayed($delay);
                }

                yield $emit($value);
            }
        });
    }

    /**
     * @param \Amp\Iterator $iterator
     * @param callable (mixed $value): mixed $onEmit
     *
     * @return \Amp\Iterator
     */
    function map(Iterator $iterator, callable $onEmit): Iterator {
        return new Producer(function (callable $emit) use ($iterator, $onEmit) {
            while (yield $iterator->advance()) {
                yield $emit($onEmit($iterator->getCurrent()));
            }
        });
    }

    /**
     * @param \Amp\Iterator $iterator
     * @param callable (mixed $value): bool $filter
     *
     * @return \Amp\Iterator
     */
    function filter(Iterator $iterator, callable $filter): Iterator {
        return new Producer(function (callable $emit) use ($iterator, $filter) {
            while (yield $iterator->advance()) {
                if ($filter($iterator->getCurrent())) {
                    yield $emit($iterator->getCurrent());
                }
            }
        });
    }

    /**
     * Creates an iterator that emits values emitted from any iterator in the array of iterators.
     *
     * @param \Amp\Iterator[] $iterators
     *
     * @return \Amp\Iterator
     */
    function merge(array $iterators): Iterator {
        $emitter = new Emitter;
        $result = $emitter->iterate();

        $coroutine = coroutine(function (Iterator $iterator) use (&$emitter) {
            while ((yield $iterator->advance()) && $emitter !== null) {
                yield $emitter->emit($iterator->getCurrent());
            }
        });

        $coroutines = [];
        foreach ($iterators as $iterator) {
            if (!$iterator instanceof Iterator) {
                throw createTypeError([Iterator::class], $iterator);
            }
            $coroutines[] = $coroutine($iterator);
        }

        Promise\all($coroutines)->onResolve(function ($exception) use (&$emitter) {
            if ($exception) {
                $emitter->fail($exception);
                $emitter = null;
            } else {
                $emitter->complete();
            }
        });

        return $result;
    }

    /**
     * Concatenates the given iterators into a single iterator, emitting values from a single iterator at a time. The
     * prior iterator must complete before values are emitted from any subsequent iterators. Iterators are concatenated
     * in the order given (iteration order of the array).
     *
     * @param array $iterators
     *
     * @return \Amp\Iterator
     */
    function concat(array $iterators): Iterator {
        foreach ($iterators as $iterator) {
            if (!$iterator instanceof Iterator) {
                throw createTypeError([Iterator::class], $iterator);
            }
        }

        $emitter = new Emitter;
        $previous = [];
        $promise = Promise\all($previous);

        $coroutine = coroutine(function (Iterator $iterator, callable $emit) {
            while (yield $iterator->advance()) {
                yield $emit($iterator->getCurrent());
            }
        });

        foreach ($iterators as $iterator) {
            $emit = coroutine(function ($value) use ($emitter, $promise) {
                static $pending = true, $failed = false;

                if ($failed) {
                    return;
                }

                if ($pending) {
                    try {
                        yield $promise;
                        $pending = false;
                    } catch (\Throwable $exception) {
                        $failed = true;
                        return; // Prior iterator failed.
                    }
                }

                yield $emitter->emit($value);
            });
            $previous[] = $coroutine($iterator, $emit);
            $promise = Promise\all($previous);
        }

        $promise->onResolve(function ($exception) use ($emitter) {
            if ($exception) {
                $emitter->fail($exception);
                return;
            }

            $emitter->complete();
        });

        return $emitter->iterate();
    }
}
