<?php

namespace Amp
{

    use React\Promise\PromiseInterface as ReactPromise;

    /**
     * Await the resolution of the given promise. The function does not return until the promise has been
     * resolved. The promise resolution value is returned or the promise failure reason is thrown.
     *
     * @template TValue
     *
     * @param Promise|ReactPromise|array<Promise|ReactPromise> $promise
     *
     * @psalm-param Promise<TValue>|ReactPromise|array<Promise<TValue>|ReactPromise> $promise
     *
     * @return mixed Promise resolution value.
     *
     * @throws \Throwable Promise failure reason.
     *
     * @psalm-return TValue|array<TValue>
     */
    function await(Promise|ReactPromise|array $promise): mixed
    {
        static $loop, $control;

        if ($loop !== Loop::get()) {
            $loop = Loop::get();
            $control = $loop->createControl();
        }

        if (!$promise instanceof Promise) {
            if ($promise instanceof ReactPromise) {
                $promise = Promise\adapt($promise);
            } else {
                $promise = Promise\all($promise);
            }
        }

        return \Fiber::await($promise, $control);
    }

    /**
     * Creates a green thread using the given callable and argument list.
     *
     * @template TValue
     *
     * @param callable(mixed ...$args):TValue $callback
     * @param mixed ...$args
     *
     * @return Promise
     *
     * @psalm-return Promise<TValue>
     */
    function async(callable $callback, mixed ...$args): Promise
    {
        $deferred = new Deferred;

        Loop::defer(static fn() => \Fiber::run(static function () use ($deferred, $callback, $args): void {
            try {
                $deferred->resolve($callback(...$args));
            } catch (\Throwable $exception) {
                $deferred->fail($exception);
            }
        }));

        return $deferred->promise();
    }

    /**
     * Returns a callable that when invoked creates a new green thread using the given callable using {@see async()},
     * passing any arguments to the function as the argument list to async() and returning promise created by async().
     *
     * @param callable $callback Green thread to create each time the function returned is invoked.
     *
     * @return callable(mixed ...$args):Promise Creates a new green thread each time the returned function is invoked.
     *     The arguments given to the returned function are passed through to the callable.
     */
    function asyncCallable(callable $callback): callable
    {
        return static fn(mixed ...$args): Promise => async($callback, ...$args);
    }

    /**
     * Returns a new function that wraps $callback in a promise/coroutine-aware function that automatically runs
     * Generators as coroutines. The returned function always returns a promise when invoked. Errors have to be handled
     * by the callback caller or they will go unnoticed.
     *
     * Use this function to create a coroutine-aware callable for a promise-aware callback caller.
     *
     * @template TReturn
     * @template TPromise
     * @template TGeneratorReturn
     * @template TGeneratorPromise
     *
     * @template TGenerator as TGeneratorReturn|Promise<TGeneratorPromise>
     * @template T as TReturn|Promise<TPromise>|\Generator<mixed, mixed, mixed, TGenerator>
     *
     * @formatter:off
     *
     * @param callable(...mixed): T $callback
     *
     * @return callable
     * @psalm-return (T is Promise ? (callable(mixed...): Promise<TPromise>) : (T is \Generator ? (TGenerator is Promise ? (callable(mixed...): Promise<TGeneratorPromise>) : (callable(mixed...): Promise<TGeneratorReturn>)) : (callable(mixed...): Promise<TReturn>)))
     *
     * @formatter:on
     *
     * @see asyncCoroutine()
     *
     * @psalm-suppress InvalidReturnType
     *
     * @deprecated No longer necessary with ext-fiber
     */
    function coroutine(callable $callback): callable
    {
        /** @psalm-suppress InvalidReturnStatement */
        return static fn(...$args): Promise => call($callback, ...$args);
    }

    /**
     * Returns a new function that wraps $callback in a promise/coroutine-aware function that automatically runs
     * Generators as coroutines. The returned function always returns void when invoked. Errors are forwarded to the
     * loop's error handler using `Amp\Promise\rethrow()`.
     *
     * Use this function to create a coroutine-aware callable for a non-promise-aware callback caller.
     *
     * @param callable(...mixed): mixed $callback
     *
     * @return callable
     * @psalm-return callable(mixed...): void
     *
     * @see coroutine()
     *
     * @deprecated No longer necessary with ext-fiber
     */
    function asyncCoroutine(callable $callback): callable
    {
        return static fn(...$args) => Promise\rethrow(call($callback, ...$args));
    }

    /**
     * Calls the given function, always returning a promise. If the function returns a Generator, it will be run as a
     * coroutine. If the function throws, a failed promise will be returned.
     *
     * @template TReturn
     * @template TPromise
     * @template TGeneratorReturn
     * @template TGeneratorPromise
     *
     * @template TGenerator as TGeneratorReturn|Promise<TGeneratorPromise>
     * @template T as TReturn|Promise<TPromise>|\Generator<mixed, mixed, mixed, TGenerator>
     *
     * @formatter:off
     *
     * @param callable(...mixed): T $callback
     * @param mixed ...$args Arguments to pass to the function.
     *
     * @return Promise
     * @psalm-return (T is Promise ? Promise<TPromise> : (T is \Generator ? (TGenerator is Promise ? Promise<TGeneratorPromise> : Promise<TGeneratorReturn>) : Promise<TReturn>))
     *
     * @formatter:on
     *
     * @deprecated No longer necessary with ext-fiber
     */
    function call(callable $callback, ...$args): Promise
    {
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
     * @param callable(...mixed): mixed $callback
     * @param mixed ...$args Arguments to pass to the function.
     *
     * @return void
     *
     * @deprecated No longer necessary with ext-fiber
     */
    function asyncCall(callable $callback, ...$args): void
    {
        Promise\rethrow(call($callback, ...$args));
    }

    /**
     * Returns a promise that is resolved in the specified number of milliseconds.
     */
    function delay(int $milliseconds): Delayed
    {
        return new Delayed($milliseconds);
    }

    /**
     * Returns the current time relative to an arbitrary point in time.
     *
     * @return int Time in milliseconds.
     */
    function getCurrentTime(): int
    {
        return Internal\getCurrentTime();
    }

    /**
     * Async sleep for the specified number of milliseconds.
     */
    function sleep(int $milliseconds): void
    {
        await(delay($milliseconds));
    }
}

namespace Amp\Promise
{

    use Amp\Deferred;
    use Amp\Failure;
    use Amp\Loop;
    use Amp\MultiReasonException;
    use Amp\Promise;
    use Amp\Success;
    use Amp\TimeoutException;
    use React\Promise\PromiseInterface as ReactPromise;
    use function Amp\await;
    use function Amp\call;
    use function Amp\Internal\createTypeError;

    /**
     * Registers a callback that will forward the failure reason to the event loop's error handler if the promise fails.
     *
     * Use this function if you neither return the promise nor handle a possible error yourself to prevent errors from
     * going entirely unnoticed.
     *
     * @param Promise|ReactPromise $promise Promise to register the handler on.
     *
     * @return void
     * @throws \TypeError If $promise is not an instance of \Amp\Promise or \React\Promise\PromiseInterface.
     *
     */
    function rethrow(Promise|ReactPromise $promise)
    {
        if (!$promise instanceof Promise) {
            $promise = adapt($promise);
        }

        $promise->onResolve(static function ($exception) {
            if ($exception) {
                throw $exception;
            }
        });
    }

    /**
     * Returns a successful promise using the given value, which can be anything other than a promise. This function
     * optimizes the case where null is used as the value, always returning the same object.
     *
     * @template TValue
     *
     * @param mixed $value Anything other than a Promise object.
     *
     * @psalm-param TValue $value
     *
     * @return Promise
     *
     * @psalm-return Promise<TValue>
     *
     * @throws \Error If a promise is given as the value.
     */
    function succeed(mixed $value = null): Promise
    {
        static $empty;

        if ($value === null) {
            return $empty ?? ($empty = new Success);
        }

        return new Success($value);
    }

    /**
     * Returns a failed promise using the given exception.
     *
     * @template TValue
     *
     * @param \Throwable $exception
     *
     * @return Promise
     *
     * @psalm-return Promise<TValue>
     */
    function fail(\Throwable $exception): Promise
    {
        return new Failure($exception);
    }

    /**
     * @param Promise|ReactPromise $promise Promise to wait for.
     *
     * @return mixed Promise success value.
     *
     * @psalm-param T $promise
     * @psalm-return (T is Promise ? TPromise : mixed)
     *
     * @throws \Throwable Promise failure reason.
     *
     * @deprecated Use {@see await()} instead.
     *
     * @template TPromise
     * @template T as Promise<TPromise>|ReactPromise
     */
    function wait(Promise|ReactPromise $promise): mixed
    {
        return await($promise);
    }

    /**
     * Creates an artificial timeout for any `Promise`.
     *
     * If the timeout expires before the promise is resolved, the returned promise fails with an instance of
     * `Amp\TimeoutException`.
     *
     * @template TReturn
     *
     * @param Promise<TReturn>|ReactPromise $promise Promise to which the timeout is applied.
     * @param int                           $timeout Timeout in milliseconds.
     *
     * @return Promise<TReturn>
     *
     * @throws \TypeError If $promise is not an instance of \Amp\Promise or \React\Promise\PromiseInterface.
     */
    function timeout(Promise|ReactPromise $promise, int $timeout): Promise
    {
        if (!$promise instanceof Promise) {
            $promise = adapt($promise);
        }

        $deferred = new Deferred;

        $watcher = Loop::delay($timeout, static function () use (&$deferred) {
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
     * Creates an artificial timeout for any `Promise`.
     *
     * If the promise is resolved before the timeout expires, the result is returned
     *
     * If the timeout expires before the promise is resolved, a default value is returned
     *
     * @template TReturn
     *
     * @param Promise<TReturn>|ReactPromise $promise Promise to which the timeout is applied.
     * @param int                           $timeout Timeout in milliseconds.
     * @param TReturn                       $default
     *
     * @return Promise<TReturn>
     *
     * @throws \TypeError If $promise is not an instance of \Amp\Promise or \React\Promise\PromiseInterface.
     */
    function timeoutWithDefault(Promise|ReactPromise $promise, int $timeout, mixed $default = null): Promise
    {
        $promise = timeout($promise, $timeout);

        return call(static function () use ($promise, $default) {
            try {
                return yield $promise;
            } catch (TimeoutException $exception) {
                return $default;
            }
        });
    }

    /**
     * Adapts any object with a done(callable $onFulfilled, callable $onRejected) or then(callable $onFulfilled,
     * callable $onRejected) method to a promise usable by components depending on placeholders implementing
     * \AsyncInterop\Promise.
     *
     * @param object $promise Object with a done() or then() method.
     *
     * @return Promise Promise resolved by the $thenable object.
     *
     * @throws \Error If the provided object does not have a then() method.
     */
    function adapt(object $promise): Promise
    {
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
     * @param Promise[]|ReactPromise[] $promises
     *
     * @return Promise
     *
     * @throws \Error If a non-Promise is in the array.
     */
    function any(array $promises): Promise
    {
        return some($promises, 0);
    }

    /**
     * Returns a promise that succeeds when all promises succeed, and fails if any promise fails. Returned
     * promise succeeds with an array of values used to succeed each contained promise, with keys corresponding to
     * the array of promises.
     *
     * @param Promise[]|ReactPromise[] $promises Array of only promises.
     *
     * @return Promise
     *
     * @throws \Error If a non-Promise is in the array.
     *
     * @template TValue
     *
     * @psalm-param array<array-key, Promise<TValue>|ReactPromise> $promises
     * @psalm-assert array<array-key, Promise<TValue>|ReactPromise> $promises $promises
     * @psalm-return Promise<array<array-key, TValue>>
     */
    function all(array $promises): Promise
    {
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
     * @param Promise[]|ReactPromise[] $promises Array of only promises.
     *
     * @return Promise
     *
     * @throws \Error If the array is empty or a non-Promise is in the array.
     */
    function first(array $promises): Promise
    {
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
     * @param Promise[]|ReactPromise[] $promises Array of only promises.
     * @param int                      $required Number of promises that must succeed for the
     *     returned promise to succeed.
     *
     * @return Promise
     *
     * @throws \Error If a non-Promise is in the array.
     */
    function some(array $promises, int $required = 1): Promise
    {
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
            $promise->onResolve(static function ($exception, $value) use (
                &$values,
                &$exceptions,
                &$pending,
                $key,
                $required,
                $deferred
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

    /**
     * Wraps a promise into another promise, altering the exception or result.
     *
     * @param Promise|ReactPromise $promise
     * @param callable             $callback
     *
     * @return Promise
     */
    function wrap($promise, callable $callback): Promise
    {
        if ($promise instanceof ReactPromise) {
            $promise = adapt($promise);
        } elseif (!$promise instanceof Promise) {
            throw createTypeError([Promise::class, ReactPromise::class], $promise);
        }

        $deferred = new Deferred();

        $promise->onResolve(static function (\Throwable $exception = null, $result) use ($deferred, $callback) {
            try {
                $result = $callback($exception, $result);
            } catch (\Throwable $exception) {
                $deferred->fail($exception);

                return;
            }

            $deferred->resolve($result);
        });

        return $deferred->promise();
    }
}

namespace Amp\Iterator
{

    use Amp\Delayed;
    use Amp\Emitter;
    use Amp\Iterator;
    use Amp\Pipeline;
    use Amp\Producer;
    use Amp\Promise;
    use function Amp\call;
    use function Amp\coroutine;
    use function Amp\Internal\createTypeError;

    /**
     * Creates an iterator from the given iterable, emitting the each value. The iterable may contain promises. If any
     * promise fails, the iterator will fail with the same reason.
     *
     * @param iterable $iterable Elements to emit.
     * @param int      $delay Delay between element emissions in milliseconds.
     *
     * @return Iterator
     *
     * @throws \TypeError If the argument is not an array or instance of \Traversable.
     */
    function fromIterable(iterable $iterable, int $delay = 0): Iterator
    {
        if ($delay) {
            return new Producer(static function (callable $emit) use ($iterable, $delay) {
                foreach ($iterable as $value) {
                    yield new Delayed($delay);
                    yield $emit($value);
                }
            });
        }

        return new Producer(static function (callable $emit) use ($iterable) {
            foreach ($iterable as $value) {
                yield $emit($value);
            }
        });
    }

    /**
     * @template TValue
     * @template TReturn
     *
     * @param Iterator<TValue> $iterator
     * @param callable (TValue $value): TReturn $onEmit
     *
     * @return Iterator<TReturn>
     */
    function map(Iterator $iterator, callable $onEmit): Iterator
    {
        return new Producer(static function (callable $emit) use ($iterator, $onEmit) {
            while (yield $iterator->advance()) {
                yield $emit($onEmit($iterator->getCurrent()));
            }
        });
    }

    /**
     * @template TValue
     *
     * @param Iterator<TValue> $iterator
     * @param callable(TValue $value):bool $filter
     *
     * @return Iterator<TValue>
     */
    function filter(Iterator $iterator, callable $filter): Iterator
    {
        return new Producer(static function (callable $emit) use ($iterator, $filter) {
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
     * @param Iterator[] $iterators
     *
     * @return Iterator
     */
    function merge(array $iterators): Iterator
    {
        $emitter = new Emitter;
        $result = $emitter->iterate();

        $coroutine = coroutine(static function (Iterator $iterator) use (&$emitter) {
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

        Promise\all($coroutines)->onResolve(static function ($exception) use (&$emitter) {
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
     * @param Iterator[] $iterators
     *
     * @return Iterator
     */
    function concat(array $iterators): Iterator
    {
        foreach ($iterators as $iterator) {
            if (!$iterator instanceof Iterator) {
                throw createTypeError([Iterator::class], $iterator);
            }
        }

        return new Producer(function (callable $emit) use ($iterators) {
            foreach ($iterators as $iterator) {
                while (yield $iterator->advance()) {
                    yield $emit($iterator->getCurrent());
                }
            }
        });
    }

    /**
     * Discards all remaining items and returns the number of discarded items.
     *
     * @template TValue
     *
     * @param Iterator $iterator
     *
     * @return Promise
     *
     * @psalm-param Iterator<TValue> $iterator
     * @psalm-return Promise<int>
     */
    function discard(Iterator $iterator): Promise
    {
        return call(static function () use ($iterator): \Generator {
            $count = 0;

            while (yield $iterator->advance()) {
                $count++;
            }

            return $count;
        });
    }

    /**
     * Collects all items from an iterator into an array.
     *
     * @template TValue
     *
     * @param Iterator $iterator
     *
     * @psalm-param Iterator<TValue> $iterator
     *
     * @return Promise
     * @psalm-return Promise<array<int, TValue>>
     */
    function toArray(Iterator $iterator): Promise
    {
        return call(static function () use ($iterator): \Generator {
            /** @psalm-var list $array */
            $array = [];

            while (yield $iterator->advance()) {
                $array[] = $iterator->getCurrent();
            }

            return $array;
        });
    }

    /**
     * @template TValue
     *
     * @param Pipeline $stream
     *
     * @psalm-param Pipeline<TValue> $pipeline
     *
     * @return Iterator
     *
     * @psalm-return Iterator<TValue>
     */
    function fromPipeline(Pipeline $stream): Iterator
    {
        return new Producer(function (callable $emit) use ($stream): \Generator {
            while (null !== $value = yield $stream->continue()) {
                yield $emit($value);
            }
        });
    }
}

namespace Amp\Pipeline
{

    use Amp\AsyncGenerator;
    use Amp\Pipeline;
    use Amp\PipelineSource;
    use Amp\Promise;
    use React\Promise\PromiseInterface as ReactPromise;
    use function Amp\await;
    use function Amp\call;
    use function Amp\coroutine;
    use function Amp\Internal\createTypeError;
    use function Amp\sleep;

    /**
     * Creates a pipeline from the given iterable, emitting the each value. The iterable may contain promises. If any
     * promise fails, the returned pipeline will fail with the same reason.
     *
     * @template TValue
     *
     * @param iterable $iterable Elements to emit.
     * @param int      $delay Delay between elements emitted in milliseconds.
     *
     * @psalm-param iterable<TValue> $iterable
     *
     * @return Pipeline
     *
     * @psalm-return Pipeline<TValue>
     *
     * @throws \TypeError If the argument is not an array or instance of \Traversable.
     */
    function fromIterable(iterable $iterable, int $delay = 0): Pipeline
    {
        return new AsyncGenerator(static function () use ($iterable, $delay): \Generator {
            foreach ($iterable as $value) {
                if ($delay) {
                    sleep($delay);
                }

                if ($value instanceof Promise || $value instanceof ReactPromise) {
                    $value = await($value);
                }

                yield $value;
            }
        });
    }

    /**
     * @template TValue
     * @template TReturn
     *
     * @param Pipeline $pipeline
     * @param callable(TValue $value):TReturn $onEmit
     *
     * @psalm-param Pipeline<TValue> $pipeline
     *
     * @return Pipeline
     *
     * @psalm-return Pipeline<TReturn>
     */
    function map(Pipeline $pipeline, callable $onEmit): Pipeline
    {
        return new AsyncGenerator(static function () use ($pipeline, $onEmit): \Generator {
            while (null !== $value = $pipeline->continue()) {
                yield $onEmit($value);
            }
        });
    }

    /**
     * @template TValue
     *
     * @param Pipeline $pipeline
     * @param callable(TValue $value):bool $filter
     *
     * @psalm-param Pipeline<TValue> $pipeline
     *
     * @return Pipeline
     *
     * @psalm-return Pipeline<TValue>
     */
    function filter(Pipeline $pipeline, callable $filter): Pipeline
    {
        return new AsyncGenerator(static function () use ($pipeline, $filter): \Generator {
            while (null !== $value = $pipeline->continue()) {
                if ($filter($value)) {
                    yield $value;
                }
            }
        });
    }

    /**
     * Creates a pipeline that emits values emitted from any pipeline in the array of pipelines.
     *
     * @param Pipeline[] $pipelines
     *
     * @return Pipeline
     */
    function merge(array $pipelines): Pipeline
    {
        $source = new PipelineSource;
        $result = $source->pipe();

        $coroutine = coroutine(static function (Pipeline $stream) use (&$source) {
            while ((null !== $value = yield $stream->continue()) && $source !== null) {
                yield $source->emit($value);
            }
        });

        $coroutines = [];
        foreach ($pipelines as $pipeline) {
            if (!$pipeline instanceof Pipeline) {
                throw createTypeError([Pipeline::class], $pipeline);
            }

            $coroutines[] = $coroutine($pipeline);
        }

        Promise\all($coroutines)->onResolve(static function ($exception) use (&$source) {
            $temp = $source;
            $source = null;

            if ($exception) {
                $temp->fail($exception);
            } else {
                $temp->complete();
            }
        });

        return $result;
    }

    /**
     * Concatenates the given pipelines into a single pipeline, emitting from a single pipeline at a time. The
     * prior pipeline must complete before values are emitted from any subsequent pipelines. Streams are concatenated
     * in the order given (iteration order of the array).
     *
     * @param Pipeline[] $pipelines
     *
     * @return Pipeline
     */
    function concat(array $pipelines): Pipeline
    {
        foreach ($pipelines as $pipeline) {
            if (!$pipeline instanceof Pipeline) {
                throw createTypeError([Pipeline::class], $pipeline);
            }
        }

        return new AsyncGenerator(function () use ($pipelines): \Generator {
            foreach ($pipelines as $stream) {
                while ($value = $stream->continue()) {
                    yield $value;
                }
            }
        });
    }

    /**
     * Discards all remaining items and returns the number of discarded items.
     *
     * @template TValue
     *
     * @param Pipeline $pipeline
     *
     * @psalm-param Pipeline<TValue> $pipeline
     *
     * @return Promise<int>
     */
    function discard(Pipeline $pipeline): Promise
    {
        return call(static function () use ($pipeline): \Generator {
            $count = 0;

            while (null !== yield $pipeline->continue()) {
                $count++;
            }

            return $count;
        });
    }

    /**
     * Collects all items from a pipeline into an array.
     *
     * @template TValue
     *
     * @param Pipeline $pipeline
     *
     * @psalm-param Pipeline<TValue> $pipeline
     *
     * @return array
     *
     * @psalm-return array<int, TValue>
     */
    function toArray(Pipeline $pipeline): array
    {
        /** @psalm-var list<TValue> $array */
        $array = [];

        while (null !== $value = $pipeline->continue()) {
            $array[] = $value;
        }

        return $array;
    }

    /**
     * Converts the given pipeline to an object implementing \Iterator.
     *
     * @template TValue
     *
     * @param Pipeline $pipeline
     *
     * @psalm-param Pipeline<TValue> $pipeline
     *
     * @return \Iterator
     *
     * @psalm-return \Iterator<TValue>
     */
    function toIterator(Pipeline $pipeline): \Iterator
    {
        while (null !== $value = $pipeline->continue()) {
            yield $value;
        }
    }
}
