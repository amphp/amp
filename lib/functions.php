<?php

namespace Amp
{

    /**
     * Await the resolution of the given promise. The function does not return until the promise has been
     * resolved. The promise resolution value is returned or the promise failure reason is thrown.
     *
     * @template TValue
     *
     * @param Promise|array<Promise> $promise
     *
     * @psalm-param Promise<TValue>|array<Promise<TValue>> $promise
     *
     * @return mixed Promise resolution value.
     *
     * @throws \Throwable Promise failure reason.
     *
     * @psalm-return TValue|array<TValue>
     */
    function await(Promise|array $promise): mixed
    {
        if (!$promise instanceof Promise) {
            $promise = Promise\all($promise);
        }

        $fiber = \Fiber::getCurrent();
        $resolved = false;

        if ($fiber) { // Awaiting from within a fiber.
            if ($fiber === Loop::getFiber()) {
                throw new \Error(\sprintf('Cannot call %s() within an event loop callback', __FUNCTION__));
            }

            $hash = spl_object_hash($fiber);
            $level = ob_get_level();

            if ($level)
            {
                Loop::setState($hash . '-key', $level);
            }

            $promise->onResolve(static function (?\Throwable $exception, mixed $value) use (&$resolved, $fiber, $hash): void {
                $resolved = true;

                if ($exception) {
                    $fiber->throw($exception);
                    return;
                }

                $fiber->resume($value);
            });

            try {
                if (Loop::getState($hash . '-key'))
                {
                    $content = ob_get_contents();

                    if ($content !== '')
                    {
                        Loop::setState($hash . '-content', $content);
                    }

                    ob_end_clean();
                }

                // Suspend the current fiber until the promise is resolved.
                $value = \Fiber::suspend();

                if (Loop::getState($hash . '-key'))
                {
                    ob_start();
                    $content = Loop::getState($hash . '-content');

                    if (!is_null($content))
                    {
                        echo $content;
                        Loop::setState($hash . '-content', null);
                    }
                }
            } finally {
                if (!$resolved) {
                    // $resolved should only be false if the fiber was manually resumed outside of the callback above.
                    throw new \Error('Fiber resumed before promise was resolved');
                }
            }

            return $value;
        }

        // Awaiting from {main}.
        $fiber = Loop::getFiber();

        $promise->onResolve(static function (?\Throwable $exception, mixed $value) use (&$resolved): void {
            $resolved = true;

            // Suspend event loop fiber to {main}.
            if ($exception) {
                \Fiber::suspend(static fn() => throw $exception);
                return;
            }

            \Fiber::suspend(static fn() => $value);
        });

        try {
            $lambda = $fiber->isStarted() ? $fiber->resume() : $fiber->start();
        } catch (\Throwable $exception) {
            throw new \Error('Exception unexpectedly thrown from event loop', 0, $exception);
        }

        if (!$resolved) {
            // $resolved should only be false if the event loop exited without resolving the promise.
            throw new \Error('Event loop suspended or exited without resolving the promise');
        }

        return $lambda();
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
    function async(callable $callback, ...$args): Promise
    {
        $placeholder = new Internal\Placeholder;

        $fiber = new \Fiber(function () use ($placeholder, $callback, $args): void {
            try {
                $placeholder->resolve($callback(...$args));
            } catch (\Throwable $exception) {
                $placeholder->fail($exception);
            }
        });

        Loop::defer(static fn() => $fiber->start());

        return new Internal\PrivatePromise($placeholder);
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
     * Executes the given callback in a new green thread similar to {@see async()}, except instead of returning a
     * promise, any exceptions thrown are forwarded to the event loop error handler. The return value of the function
     * is discarded.
     *
     * @param callable(mixed ...$args):void $callback
     * @param mixed ...$args
     */
    function defer(callable $callback, mixed ...$args): void
    {
        $fiber = new \Fiber(static function () use ($callback, $args): void {
            try {
                $callback(...$args);
            } catch (\Throwable $exception) {
                Loop::defer(static fn() => throw $exception);
            }
        });

        Loop::defer(static fn() => $fiber->start());
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
    function call(callable $callback, mixed ...$args): Promise
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
    function asyncCall(callable $callback, mixed ...$args): void
    {
        Promise\rethrow(call($callback, ...$args));
    }

    /**
     * Async sleep for the specified number of milliseconds.
     *
     * @param int $milliseconds Number of milliseconds to sleep.
     */
    function delay(int $milliseconds): void
    {
        await(new Delayed($milliseconds));
    }

    /**
     * Await the arrival of a signal to the process.
     *
     * @param int $signal Required signal to await.
     * @param int ...$signals Additional signals to await.
     *
     * @return int The signal number received.
     */
    function trap(int $signal, int ...$signals): int
    {
        return await(new SignalTrap($signal, ...$signals));
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
}

namespace Amp\Promise
{

    use Amp\Deferred;
    use Amp\Loop;
    use Amp\MultiReasonException;
    use Amp\Promise;
    use Amp\Success;
    use Amp\TimeoutException;
    use function Amp\async;
    use function Amp\await;
    use function Amp\Internal\createTypeError;

    /**
     * Registers a callback that will forward the failure reason to the event loop's error handler if the promise fails.
     *
     * Use this function if you neither return the promise nor handle a possible error yourself to prevent errors from
     * going entirely unnoticed.
     *
     * @param Promise $promise Promise to register the handler on.
     *
     * @return void
     * @throws \TypeError If $promise is not an instance of \Amp\Promise.
     *
     */
    function rethrow(Promise $promise): void
    {
        $promise->onResolve(static function (?\Throwable $exception): void {
            if ($exception) {
                throw $exception;
            }
        });
    }

    /**
     * @param Promise $promise Promise to wait for.
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
     * @template T as Promise<TPromise>
     */
    function wait(Promise $promise): mixed
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
     * @param Promise<TReturn> $promise Promise to which the timeout is applied.
     * @param int                           $timeout Timeout in milliseconds.
     *
     * @return Promise<TReturn>
     *
     * @throws \TypeError If $promise is not an instance of \Amp\Promise.
     */
    function timeout(Promise $promise, int $timeout): Promise
    {
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
     * @param Promise<TReturn> $promise Promise to which the timeout is applied.
     * @param int              $timeout Timeout in milliseconds.
     * @param TReturn          $default
     *
     * @return Promise<TReturn>
     *
     * @throws \TypeError If $promise is not an instance of \Amp\Promise.
     */
    function timeoutWithDefault(Promise $promise, int $timeout, mixed $default = null): Promise
    {
        $promise = timeout($promise, $timeout);

        return async(static function () use ($promise, $default) {
            try {
                return await($promise);
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
     * @param Promise[] $promises
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
     * @param Promise[] $promises Array of only promises.
     *
     * @return Promise
     *
     * @throws \Error If a non-Promise is in the array.
     *
     * @template TValue
     *
     * @psalm-param array<array-key, Promise<TValue>> $promises
     * @psalm-assert array<array-key, Promise<TValue>> $promises $promises
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
            if (!$promise instanceof Promise) {
                throw createTypeError([Promise::class], $promise);
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
     * @param Promise[] $promises Array of only promises.
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
            if (!$promise instanceof Promise) {
                throw createTypeError([Promise::class], $promise);
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
     * @param Promise[] $promises Array of only promises.
     * @param int       $required Number of promises that must succeed for the
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
            if (!$promise instanceof Promise) {
                throw createTypeError([Promise::class], $promise);
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
     * @param Promise  $promise
     * @param callable $callback
     *
     * @return Promise
     *
     * @deprecated Use {@see await()} instead.
     */
    function wrap(Promise $promise, callable $callback): Promise
    {
        return async(function () use ($promise, $callback) {
            try {
                return $callback(null, await($promise));
            } catch (\Throwable $exception) {
                return $callback($exception, null);
            }
        });
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
    use function Amp\async;
    use function Amp\asyncCallable;
    use function Amp\await;
    use function Amp\delay;
    use function Amp\Internal\createTypeError;

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
                    delay($delay);
                }

                if ($value instanceof Promise) {
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

        $coroutine = asyncCallable(static function (Pipeline $stream) use (&$source) {
            while ((null !== $value = $stream->continue()) && $source !== null) {
                $source->yield($value);
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
        return async(static function () use ($pipeline): int {
            $count = 0;

            while (null !== $pipeline->continue()) {
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
        return \iterator_to_array($pipeline);
    }
}
