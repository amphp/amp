<?php

namespace Amp;

/**
 * Get the global singleton event reactor instance
 */
function getReactor() {
    static $reactor;
    return $reactor ?: ($reactor = chooseReactor());
}

/**
 * Select the most appropriate event reactor given the current execution environment
 */
function chooseReactor() {
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
function tick($noWait = false) {
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
function immediately(callable $func) {
    return getReactor()->immediately($func);
}

/**
 * Schedule a callback to execute once
 *
 * NOTE: Watchers registered using this function are automatically garbage collected after execution.
 */
function once(callable $func, $millisecondDelay) {
    return getReactor()->once($func, $millisecondDelay);
}

/**
 * Schedule a recurring callback to execute every $interval seconds until cancelled
 *
 * IMPORTANT: Watchers registered using this function must be manually cleared using cancel() to
 * free the associated memory. Failure to cancel repeating watchers (even if disable() is used)
 * will lead to memory leaks.
 */
function repeat(callable $func, $millisecondDelay) {
    return getReactor()->repeat($func, $millisecondDelay);
}

/**
 * Enable a disabled timer or stream IO watcher
 *
 * Calling enable() on an already-enabled watcher will have no effect.
 */
function enable($watcherId) {
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
function disable($watcherId) {
    getReactor()->disable($watcherId);
}

/**
 * Cancel an existing timer/stream watcher
 *
 * Calling cancel() on a non-existent watcher ID will have no effect.
 */
function cancel($watcherId) {
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
function onReadable($stream, callable $func, $enableNow = true) {
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
function onWritable($stream, callable $func, $enableNow = true) {
    getReactor()->onWritable($stream, $func, $enableNow);
}

/**
 * React to process control signals
 */
function onSignal($signo, callable $onSignal) {
    /**
     * @var $reactor \Amp\SignalReactor
     */
    $reactor = getReactor();
    if ($reactor instanceof SignalReactor) {
        return $reactor->onSignal($signo, $onSignal);
    } else {
        throw new \RuntimeException(
            'Your PHP environment does not support signal handling. Please install pecl/libevent or the php-uv extension'
        );
    }
}

/**
 * If any one of the Promises fails the resulting Promise will fail. Otherwise
 * the resulting Promise succeeds with an array matching keys from the input array
 * to their resolved values.
 */
function all(array $promises) {
    if (empty($promises)) {
        return new Success([]);
    }

    $results    = [];
    $remaining  = count($promises);
    $promisor   = new Deferred;

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

    return $promisor->promise();
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
function some(array $promises) {
    if (empty($promises)) {
        return new Failure(new \LogicException(
            'No promises or values provided for resolution'
        ));
    }

    $errors    = [];
    $results   = [];
    $remaining = count($promises);
    $promisor  = new Deferred;

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

    return $promisor->promise();
}

/**
 * Resolves with a two-item array delineating successful and failed Promise results.
 *
 * This function is the same as some() with the notable exception that it will never fail even
 * if all promises in the array resolve unsuccessfully.
 */
function any(array $promises) {
    if (empty($promises)) {
        return new Success([[], []]);
    }

    $results   = [];
    $errors    = [];
    $remaining = count($promises);
    $promisor  = new Deferred;

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

    return $promisor->promise();
}

/**
 * Resolves with the first successful Promise value. The resulting Promise will only fail if all
 * Promise values in the group fail or if the initial Promise array is empty.
 */
function first(array $promises) {
    if (empty($promises)) {
        return new Failure(new \LogicException(
            'No promises or values provided for resolution'
        ));
    }

    $remaining  = count($promises);
    $isComplete = false;
    $promisor   = new Deferred;

    foreach ($promises as $resolvable) {
        if (!$resolvable instanceof Promise) {
            $promisor->succeed($resolvable);
            break;
        }

        $promise->when(function($error, $result) use (&$remaining, &$isComplete, $promisor) {
            if ($isComplete) {
                // we don't care about Deferreds that resolve after the first
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

    return $promisor->promise();
}

/**
 * Map promised deferred values using the specified functor
 */
function map(array $promises, callable $functor) {
    if (empty($promises)) {
        return new Success([]);
    }

    $results   = [];
    $remaining = count($promises);
    $promisor  = new Deferred;

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

    return $promisor->promise();
}

/**
 * Filter deferred values using the specified functor
 *
 * If the functor returns a truthy value the resolved promise result is retained, otherwise it is
 * discarded. Array keys are retained for any results not filtered out by the functor.
 */
function filter(array $promises, callable $functor) {
    if (empty($promises)) {
        return new Success([]);
    }

    $results   = [];
    $remaining = count($promises);
    $promisor  = new Deferred;

    foreach ($promises as $key => $resolvable) {
        $promise = ($resolvable instanceof Promise) ? $resolvable : new Success($resolvable);
        $promise->when(function($error, $result) use (&$remaining, &$results, $key, $promisor, $functor) {
            if (empty($remaining)) {
                // If the deferred result already failed we don't bother.
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

    return $promisor->promise();
}

/**
 * Pipe the promised value through the specified functor once it resolves
 *
 * @return \Amp\Promise
 */
function pipe($promise, callable $functor) {
    if (!($promise instanceof Promise)) {
        $promise = new Success($promise);
    }
    $promisor = new Deferred;
    $promise->when(function($error, $result) use ($promisor, $functor) {
        if ($error) {
            $promisor->fail($error);
            return;
        }
        try {
            $promisor->succeed(call_user_func($functor, $result));
        } catch (\Exception $error) {
            $promisor->fail($error);
        }
    });

    return $promisor->promise();
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
function coroutine(callable $func, Reactor $reactor = null, callable $promisifier = null) {
    return function($data) use ($func, $reactor, $promisifier) {
        $result = $func($data);
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
function resolve(\Generator $generator, Reactor $reactor = null, callable $promisifier = null) {
    $cs = new \StdClass;
    $cs->reactor = $reactor ?: getReactor();
    $cs->promisor = new Deferred;
    $cs->generator = $generator;
    $cs->promisifier = $promisifier;
    $cs->currentPromise = null;
    $cs->returnValue = null;

    __coroutineAdvance($cs);

    return $cs->promisor->promise();
}

function __coroutineAdvance($cs) {
    try {
        if (!$cs->generator->valid()) {
            $cs->promisor->succeed($cs->returnValue);
            return;
        }

        $yielded = $cs->generator->current();

        if (!isset($yielded)) {
            // nothing to do ... jump to the end
        } elseif (($key = $cs->generator->key()) === "return") {
            $cs->returnValue = $yielded;
        } elseif ($yielded instanceof Promise) {
            $cs->currentPromise = $yielded;
        } elseif ($yielded instanceof Streamable) {
            $cs->currentPromise = resolve($yielded->buffer(), $cs->reactor);
        } elseif ($cs->promisifier) {
            $promise = call_user_func($cs->promisifier, $cs->generator, $key, $yielded);
            if ($promise instanceof Promise) {
                $cs->currentPromise = $promise;
            } else {
                $cs->promisor->fail(new \DomainException(sprintf(
                    "Invalid promisifier yield of type %s; Promise|null expected",
                    is_object($promise) ? get_class($promise) : gettype($promise)
                )));
                return;
            }
        } else {
            $cs->promisor->fail(new \DomainException(
                __generateYieldError($cs->generator, $key, $yielded)
            ));
            return;
        }

        $cs->reactor->immediately("Amp\__coroutineNextTick", ["callback_data" => $cs]);

    } catch (\Exception $uncaught) {
        $cs->promisor->fail($uncaught);
    }
}

function __coroutineNextTick($reactor, $watcherId, $cs) {
    if ($promise = $cs->currentPromise) {
        $cs->currentPromise = null;
        $promise->when("Amp\__coroutineSend", $cs);
    } else {
        __coroutineSend(null, null, $cs);
    }
}

function __coroutineSend($error, $result, $cs) {
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

function __generateYieldError(\Generator $generator, $key, $yielded) {
    $reflectionGen = new \ReflectionGenerator($generator);
    $executingGen = $reflectionGen->getExecutingGenerator();
    if ($isSubgenerator = ($executingGen !== $generator)) {
        $reflectionGen = new \ReflectionGenerator($executingGen);
    }

    return sprintf(
        "Unexpected Generator yield (Promise|null expected); %s yielded at key %s on line %s in %s",
        (is_object($yielded) ? get_class($yielded) : gettype($yielded)),
        $key,
        $reflectionGen->getExecutingLine(),
        $reflectionGen->getExecutingFile()
    );
}
