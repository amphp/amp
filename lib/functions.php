<?php

namespace Amp;

/**
 * Get the global singleton event reactor instance
 */
function getReactor(): Reactor {
    static $reactor;
    return $reactor ?: ($reactor = chooseReactor());
}

/**
 * Select the most appropriate event reactor given the current execution environment
 */
function chooseReactor(): Reactor {
    if (extension_loaded('uv')) {
        return new UvReactor;
    } else {
        return new NativeReactor;
    }
}

/**
 * Start an event reactor and assume program flow control
 */
function run(callable $onStart = null) {
    getReactor()->run($onStart);
}

/**
 * Execute a single event loop iteration
 */
function tick(bool $noWait = false) {
    getReactor()->tick($noWait);
}

/**
 * Stop the event reactor
 *
 * @return void
 */
function stop() {
    getReactor()->stop();
}

/**
 * Schedule a callback for immediate invocation in the next event loop iteration
 *
 * NOTE: Watchers registered using this function are automatically garbage collected after execution.
 */
function immediately(callable $func): string {
    return getReactor()->immediately($func);
}

/**
 * Schedule a callback to execute once
 *
 * NOTE: Watchers registered using this function are automatically garbage collected after execution.
 */
function once(callable $func, int $millisecondDelay): string {
    return getReactor()->once($func, $millisecondDelay);
}

/**
 * Schedule a recurring callback to execute every $interval seconds until cancelled
 *
 * IMPORTANT: Watchers registered using this function must be manually cleared using cancel() to
 * free the associated memory. Failure to cancel repeating watchers (even if disable() is used)
 * will lead to memory leaks.
 */
function repeat(callable $func, int $millisecondDelay): string {
    return getReactor()->repeat($func, $millisecondDelay);
}

/**
 * Enable a disabled timer or stream IO watcher
 *
 * Calling enable() on an already-enabled watcher will have no effect.
 */
function enable(string $watcherId) {
    getReactor()->enable($watcherId);
}

/**
 * Temporarily disable (but don't cancel) an existing timer/stream watcher
 *
 * Calling disable() on a nonexistent or previously-disabled watcher will have no effect.
 *
 * NOTE: Disabling a repeating or stream watcher is not sufficient to free associated resources.
 * When the watcher is no longer needed applications must still use cancel() to clear related
 * memory and avoid leaks.
 */
function disable(string $watcherId) {
    getReactor()->disable($watcherId);
}

/**
 * Cancel an existing timer/stream watcher
 *
 * Calling cancel() on a non-existent watcher ID will have no effect.
 */
function cancel(string $watcherId) {
    getReactor()->cancel($watcherId);
}

/**
 * Watch a stream IO resource for readable data and trigger the specified callback when actionable
 *
 * IMPORTANT: Watchers registered using this function must be manually cleared using cancel() to
 * free the associated memory. Failure to cancel repeating watchers (even if disable() is used)
 * will lead to memory leaks.
 *
 * @param resource $stream
 */
function onReadable($stream, callable $func, bool $enableNow = true): string {
    getReactor()->onReadable($stream, $func, $enableNow);
}

/**
 * Watch a stream IO resource for writability and trigger the specified callback when actionable
 *
 * NOTE: Sockets are essentially "always writable" (as long as their write buffer is not full).
 * Therefore, it's critical that applications disable or cancel write watchers as soon as all data
 * is written or the watcher will trigger endlessly and hammer the CPU.
 *
 * IMPORTANT: Watchers registered using this function must be manually cleared using cancel() to
 * free the associated memory. Failure to cancel repeating watchers (even if disable() is used)
 * will lead to memory leaks.
 *
 * @param resource $stream
 */
function onWritable($stream, callable $func, bool $enableNow = true): string {
    getReactor()->onWritable($stream, $func, $enableNow);
}

/**
 * React to process control signals
 */
function onSignal(int $signo, callable $onSignal): string {
    /**
     * @var $reactor \Amp\SignalReactor
     */
    $reactor = getReactor();
    if ($reactor instanceof SignalReactor) {
        return $reactor->onSignal($signo, $onSignal);
    } else {
        throw new \RuntimeException(
            'Your PHP environment does not support signal handling. Please install the php-uv extension'
        );
    }
}

/**
 * If any one of the Promises fails the resulting Promise will fail. Otherwise
 * the resulting Promise succeeds with an array matching keys from the input array
 * to their resolved values.
 */
function all(array $promises): Promise {
    if (empty($promises)) {
        return new Success([]);
    }

    $results    = [];
    $remaining  = count($promises);
    $promisor   = new Future;

    foreach ($promises as $key => $resolvable) {
        if (!$resolvable instanceof Promise) {
            $resolvable = new Success($resolvable);
        }

        $resolvable->when(function($error, $result) use (&$remaining, &$results, $key, $promisor) {
            // If the promisor already failed don't bother
            if (empty($remaining)) {
                return;
            }

            if ($error) {
                $remaining = 0;
                $promisor->fail($error);
                return;
            }

            $results[$key] = $result;
            if (--$remaining === 0) {
                $promisor->succeed($results);
            }
        });
    }

    // We can return $promisor directly because the Future Promisor implementation
    // also implements Promise for convenience
    return $promisor;
}

/**
 * Resolves with a two-item array delineating successful and failed Promise results.
 *
 * The resulting Promise will only fail if ALL of the Promise values fail or if the
 * Promise array is empty.
 *
 * The resulting Promise is resolved with an indexed two-item array of the following form:
 *
 *     [$arrayOfFailures, $arrayOfSuccesses]
 *
 * The individual keys in the resulting arrays are preserved from the initial Promise array
 * passed to the function for evaluation.
 */
function some(array $promises): Promise {
    if (empty($promises)) {
        return new Failure(new \LogicException(
            'No promises or values provided for resolution'
        ));
    }

    $errors    = [];
    $results   = [];
    $remaining = count($promises);
    $promisor  = new Future;

    foreach ($promises as $key => $resolvable) {
        if (!$resolvable instanceof Promise) {
            $resolvable = new Success($resolvable);
        }

        $resolvable->when(function($error, $result) use (&$remaining, &$results, &$errors, $key, $promisor) {
            if ($error) {
                $errors[$key] = $error;
            } else {
                $results[$key] = $result;
            }

            if (--$remaining > 0) {
                return;
            } elseif (empty($results)) {
                $promisor->fail(new \RuntimeException(
                    'All promises failed'
                ));
            } else {
                $promisor->succeed([$errors, $results]);
            }
        });
    }

    // We can return $promisor directly because the Future Promisor implementation
    // also implements Promise for convenience
    return $promisor;
}

/**
 * Resolves with a two-item array delineating successful and failed Promise results.
 *
 * This function is the same as some() with the notable exception that it will never fail even
 * if all promises in the array resolve unsuccessfully.
 */
function any(array $promises): Promise {
    if (empty($promises)) {
        return new Success([], []);
    }

    $results   = [];
    $errors    = [];
    $remaining = count($promises);
    $promisor  = new Future;

    foreach ($promises as $key => $resolvable) {
        if (!$resolvable instanceof Promise) {
            $resolvable = new Success($resolvable);
        }

        $resolvable->when(function($error, $result) use (&$remaining, &$results, &$errors, $key, $promisor) {
            if ($error) {
                $errors[$key] = $error;
            } else {
                $results[$key] = $result;
            }

            if (--$remaining === 0) {
                $promisor->succeed([$errors, $results]);
            }
        });
    }

    // We can return $promisor directly because the Future Promisor implementation
    // also implements Promise for convenience
    return $promisor;
}

/**
 * Resolves with the first successful Promise value. The resulting Promise will only fail if all
 * Promise values in the group fail or if the initial Promise array is empty.
 */
function first(array $promises): Promise {
    if (empty($promises)) {
        return new Failure(new \LogicException(
            'No promises or values provided for resolution'
        ));
    }

    $remaining  = count($promises);
    $isComplete = false;
    $promisor   = new Future;

    foreach ($promises as $resolvable) {
        if (!$resolvable instanceof Promise) {
            $promisor->succeed($resolvable);
            break;
        }

        $promise->when(function($error, $result) use (&$remaining, &$isComplete, $promisor) {
            if ($isComplete) {
                // we don't care about Futures that resolve after the first
                return;
            } elseif ($error && --$remaining === 0) {
                $promisor->fail(new \RuntimeException(
                    'All promises failed'
                ));
            } elseif (empty($error)) {
                $isComplete = true;
                $promisor->succeed($result);
            }
        });
    }

    // We can return $promisor directly because the Future Promisor implementation
    // also implements Promise for convenience
    return $promisor;
}

/**
 * Map promised future values using the specified functor
 */
function map(array $promises, callable $functor): Promise {
    if (empty($promises)) {
        return new Success([]);
    }

    $results   = [];
    $remaining = count($promises);
    $promisor  = new Future;

    foreach ($promises as $key => $resolvable) {
        $promise = ($resolvable instanceof Promise) ? $resolvable : new Success($resolvable);
        $promise->when(function($error, $result) use (&$remaining, &$results, $key, $promisor, $functor) {
            if (empty($remaining)) {
                // If the promise already failed we don't bother.
                return;
            }
            if ($error) {
                $remaining = 0;
                $promisor->fail($error);
                return;
            }

            try {
                $results[$key] = $functor($result);
                if (--$remaining === 0) {
                    $promisor->succeed($results);
                }
            } catch (\Exception $error) {
                $remaining = 0;
                $promisor->fail($error);
            }
        });
    }

    // We can return $promisor directly because the Future Promisor implementation
    // also implements Promise for convenience
    return $promisor;
}

/**
 * Filter future values using the specified functor
 *
 * If the functor returns a truthy value the resolved promise result is retained, otherwise it is
 * discarded. Array keys are retained for any results not filtered out by the functor.
 */
function filter(array $promises, callable $functor): Promise {
    if (empty($promises)) {
        return new Success([]);
    }

    $results   = [];
    $remaining = count($promises);
    $promisor  = new Future;

    foreach ($promises as $key => $resolvable) {
        $promise = ($resolvable instanceof Promise) ? $resolvable : new Success($resolvable);
        $promise->when(function($error, $result) use (&$remaining, &$results, $key, $promisor, $functor) {
            if (empty($remaining)) {
                // If the future result already failed we don't bother.
                return;
            }
            if ($error) {
                $remaining = 0;
                $promisor->fail($error);
                return;
            }
            try {
                if ($functor($result)) {
                    $results[$key] = $result;
                }
                if (--$remaining === 0) {
                    $promisor->succeed($results);
                }
            } catch (\Exception $error) {
                $promisor->fail($error);
            }
        });
    }

    // We can return $promisor directly because the Future Promisor implementation
    // also implements Promise for convenience
    return $promisor;
}

/**
 * Block script execution indefinitely until the specified Promise resolves
 *
 * In the event of promise failure this method will throw the exception responsible for the failure.
 * Otherwise the promise's resolved value is returned.
 *
 * If the optional event reactor instance is not specified then the global default event reactor
 * is used. Applications should be very careful to avoid instantiating multiple event reactors as
 * this can lead to hard-to-debug failures. If the async value producer uses a different event
 * reactor instance from that specified in this method the wait() call will never return.
 *
 * @throws \Exception if the promise fails
 */
function wait(Promise $promise, Reactor $reactor = null) {
    $isWaiting = true;
    $resolvedError = null;
    $resolvedResult = null;

    $promise->when(function($error, $result) use (&$isWaiting, &$resolvedError, &$resolvedResult) {
        $isWaiting = false;
        $resolvedError = $error;
        $resolvedResult = $result;
    });

    $reactor = $reactor ?: getReactor();
    while ($isWaiting) {
        $reactor->tick();
    }

    if ($resolvedError) {
        throw $resolvedError;
    }

    return $resolvedResult;
}

/**
 * Return a function that will be resolved as a coroutine once invoked
 */
function coroutine(callable $func, Reactor $reactor = null, callable $promisifier = null): callable {
    return function(...$args) use ($func, $reactor, $promisifier) {
        $result = $func(...$args);
        return ($result instanceof \Generator)
            ? resolve($result, $reactor, $promisifier)
            : $result;
    };
}

/**
 * Resolve a Generator function as a coroutine
 *
 * Upon resolution the Generator return value is used to succeed the promised result. If an
 * error occurs during coroutine resolution the promise fails.
 */
function resolve(\Generator $generator, Reactor $reactor = null, callable $promisifier = null): Promise {
    $cs = new class {
        use Struct;
        public $reactor;
        public $promisor;
        public $generator;
        public $promisifier;
    };
    $cs->reactor = $reactor ?: getReactor();
    $cs->promisor = new Future;
    $cs->generator = $generator;
    $cs->promisifier = $promisifier;
    __coroutineAdvance($cs);

    return $cs->promisor->promise();
}

function __coroutineAdvance($cs) {
    try {
        if ($cs->generator->valid()) {
            $promise = __coroutinePromisify($cs);
            $cs->reactor->immediately(function() use ($cs, $promise) {
                $promise->when(function($error, $result) use ($cs) {
                    __coroutineSend($cs, $error, $result);
                });
            });
        } else {
            /* @TODO Remove $cs->returnValue check once "return" key support is removed */
            $cs->promisor->succeed($cs->returnValue ?? $cs->generator->getReturn());
        }
    } catch (\Exception $uncaught) {
        $cs->promisor->fail($uncaught);
    }
}

function __coroutineSend($cs, \Exception $error = null, $result = null) {
    try {
        if ($error) {
            $cs->generator->throw($error);
        } else {
            $cs->generator->send($result);
        }
        __coroutineAdvance($cs);
    } catch (\Exception $uncaught) {
        $cs->promisor->fail($uncaught);
    }
}

function __coroutinePromisify($cs) : Promise {
    $yielded = $cs->generator->current();

    if (!isset($yielded)) {
        return new Success;
    }
    
    $key = $cs->generator->key();

    /**
     * Allow "fake generator returns" for compatibility with code migrating
     * from PHP5.x using the "return" yield key.
     *
     * @TODO Remove $cs->returnValue check once "return" key support is removed
     */
    if ($key === "return") {
        trigger_error(
            "Returning coroutine results via `yield \"return\" => \$foo` is deprecated; please " .
            "use return statements directly in generator functions"
            E_USER_DEPRECATED
        );
        $cs->returnValue = $yielded;
        return new Success($yielded);
    }

    if ($yielded instanceof Promise) {
        return $yielded;
    }

    // Allow custom promisifier callables to create Promise from
    // the yielded key/value for extension use-cases
    if ($cs->promisifier) {
        return ($cs->promisifier)($key, $yielded);
    }

    return new Failure(new \DomainException(
        sprintf(
            "Unexpected value of type %s yielded; Promise expected",
            is_object($yielded) ? get_class($yielded) : gettype($yielded)
        )
    ));
}
