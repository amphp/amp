<?php

namespace Amp;

/**
 * Get the global singleton event reactor instance
 *
 * @param bool $forceNew If true return a new Reactor instance (but don't store it for future use)
 * @return Reactor
 */
function getReactor() {
    static $reactor;

    if ($reactor) {
        return $reactor;
    } else {
        return $reactor = chooseReactor();
    }
}

/**
 * Select the most appropriate event reactor given the current execution environment
 *
 * @return LibeventReactor|NativeReactor|UvReactor
 */
function chooseReactor() {
    if (extension_loaded('uv')) {
        return new UvReactor;
    } elseif (extension_loaded('libevent')) {
        return new LibeventReactor;
    } else {
        return new NativeReactor;
    }
}

/**
 * Start an event reactor and assume program flow control
 *
 * @param callable $onStart Optional callback to invoke immediately upon reactor start
 * @return void
 */
function run(callable $onStart = null) {
    return getReactor()->run($onStart);
}

/**
 * Execute a single event loop iteration
 *
 * @param bool $noWait
 * @return void
 */
function tick($noWait = false) {
    return getReactor()->tick($noWait);
}

/**
 * Stop the event reactor
 *
 * @return void
 */
function stop() {
    return getReactor()->stop();
}

/**
 * Schedule a callback for immediate invocation in the next event loop iteration
 *
 * Watchers registered using this function will be automatically garbage collected after execution.
 *
 * @param callable $func Any valid PHP callable
 * @return int Returns the unique watcher ID for disable/enable/cancel
 */
function immediately(callable $func) {
    return getReactor()->immediately($func);
}

/**
 * Schedule a callback to execute once
 *
 * Watchers registered using this function will be automatically garbage collected after execution.
 *
 * @param callable $func Any valid PHP callable
 * @param int $msDelay The delay in milliseconds before the callback will trigger (may be zero)
 * @return int Returns the unique watcher ID for disable/enable/cancel
 */
function once(callable $func, $msDelay) {
    return getReactor()->once($func, $msDelay);
}

/**
 * Schedule a recurring callback to execute every $interval seconds until cancelled
 *
 * IMPORTANT: Watchers registered using this function must be manually cleared using cancel() to
 * free the associated memory. Failure to cancel repeating watchers (even if disable() is used)
 * will lead to memory leaks.
 *
 * @param callable $func Any valid PHP callable
 * @param int $msDelay The delay in milliseconds in-between callback invocations (may be zero)
 * @return int Returns the unique watcher ID for disable/enable/cancel
 */
function repeat(callable $func, $msDelay) {
    return getReactor()->repeat($func, $msDelay);
}

/**
 * Schedule an event to trigger once at the specified time
 *
 * Watchers registered using this function will be automatically garbage collected after execution.
 *
 * @param callable $func Any valid PHP callable
 * @param string $timeString Any string that can be parsed by strtotime() and is in the future
 * @return int Returns the unique watcher ID for disable/enable/cancel
 */
function at(callable $func, $timeString) {
    return getReactor()->at($func, $timeString);
}

/**
 * Enable a disabled timer or stream IO watcher
 *
 * Calling enable() on an already-enabled watcher will have no effect.
 *
 * @param int $watcherId
 * @return void
 */
function enable($watcherId) {
    return getReactor()->enable($watcherId);
}

/**
 * Temporarily disable (but don't cancel) an existing timer/stream watcher
 *
 * Calling disable() on a nonexistent or previously-disabled watcher will have no effect.
 *
 * NOTE: Disabling a repeating or stream watcher is not sufficient to free associated resources.
 * When the watcher is no longer needed applications must still use cancel() to clear related
 * memory and avoid leaks.
 *
 * @param int $watcherId
 * @return void
 */
function disable($watcherId) {
    return getReactor()->disable($watcherId);
}

/**
 * Cancel an existing timer/stream watcher
 *
 * Calling cancel() on a non-existent watcher will have no effect.
 *
 * @param int $watcherId
 * @return void
 */
function cancel($watcherId) {
    return getReactor()->cancel($watcherId);
}

/**
 * Watch a stream IO resource for readable data and trigger the specified callback when actionable
 *
 * IMPORTANT: Watchers registered using this function must be manually cleared using cancel() to
 * free the associated memory. Failure to cancel repeating watchers (even if disable() is used)
 * will lead to memory leaks.
 *
 * @param resource $stream A stream resource to watch for readable data
 * @param callable $func Any valid PHP callable
 * @param bool $enableNow Should the watcher be enabled now or held for later use?
 * @return int Returns the unique watcher ID for disable/enable/cancel
 */
function onReadable($stream, callable $func, $enableNow = true) {
    return getReactor()->onReadable($stream, $func, $enableNow);
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
 * @param resource $stream A stream resource to watch for writable data
 * @param callable $func Any valid PHP callable
 * @param bool $enableNow Should the watcher be enabled now or held for later use?
 * @return int Returns the unique watcher ID for disable/enable/cancel
 */
function onWritable($stream, callable $func, $enableNow = true) {
    return getReactor()->onWritable($stream, $func, $enableNow);
}

/**
 * Resolve the specified generator
 *
 * Upon resolution the final yielded value is used to succeed the returned promise. If an
 * error occurs the returned promise is failed appropriately.
 *
 * @param \Generator $generator
 * @param Reactor $reactor optional reactor instance (uses global reactor if not specified)
 * @return Promise
 */
function coroutine(\Generator $generator, $reactor = null) {
    $reactor = $reactor ?: getReactor();
    return $reactor->coroutine($generator);
}

/**
 * React to process control signals
 *
 * @param int $signo The signal number to watch for
 * @param callable $onSignal
 * @throws \RuntimeException if the current environment cannot support signal handling
 * @return int Returns a unique integer watcher ID
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
 *
 * @param array[Promise] $promises
 * @return Promise
 */
function all(array $promises) {
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
 *
 * @param array[Promise] $promises
 * @return Promise
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
 *
 * @param array[Promise] $promises
 * @return Promise
 */
function any(array $promises) {
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
 *
 * @param array[Promise] $promises
 * @return Promise
 */
function first(array $promises) {
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
 *
 * @param array[Promise] $promises
 * @param callable $functor
 * @return Promise
 */
function map(array $promises, callable $functor) {
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
                // If the future already failed we don't bother.
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
 *
 * @param array[Promise] $promises
 * @param callable $functor
 * @return Promise
 */
function filter(array $promises, callable $functor) {
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
 * @param Promise $promise A promise on which to wait for resolution
 * @param Reactor $reactor An optional event reactor instance
 * @throws \Exception
 * @return mixed
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

// === DEPRECATED FUNCTIONS ========================================================================

/**
 * Get the global singleton event reactor instance
 *
 * Note that the $factory callable is only invoked if no global reactor has yet been initialized.
 *
 * !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
 * === THIS FUNCTION IS DEPRECATED ===
 * !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
 *
 * @param callable $factory Optional factory callable for initializing a reactor
 * @return Reactor
 */
function reactor(callable $factory = null) {
    static $reactor;

    $msg = 'This function is deprecated and scheduled for removal. Please use Amp\\getReactor()';
    trigger_error($msg, E_USER_DEPRECATED);

    if ($reactor) {
        return $reactor;
    } elseif ($factory) {
        return ($reactor = $factory());
    } elseif (extension_loaded('uv')) {
        return ($reactor = new UvReactor);
    } elseif (extension_loaded('libevent')) {
        return ($reactor = new LibeventReactor);
    } else {
        return ($reactor = new NativeReactor);
    }
}
