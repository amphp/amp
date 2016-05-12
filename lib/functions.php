<?php

namespace Amp;

/**
 * Retrieve the application-wide event reactor instance
 *
 * @param \Amp\Reactor $assign Optionally specify a new default event reactor instance
 * @return \Amp\Reactor Returns the application-wide reactor instance
 */
function reactor(Reactor $assign = null) {
    static $reactor;
    if ($assign) {
        return ($reactor = $assign);
    } elseif ($reactor) {
        return $reactor;
    } else {
        return ($reactor = driver());
    }
}

/**
 * Create a new event reactor best-suited for the current environment
 *
 * @return \Amp\Reactor
 */
function driver() {
    if (\extension_loaded("uv")) {
        return new UvReactor;
    } elseif (\extension_loaded("ev")) {
        return new EvReactor;
    } elseif (\extension_loaded("libevent")) {
        return new LibeventReactor;
    } else {
        return new NativeReactor;
    }
}

/**
 * Start the event reactor and assume program flow control
 *
 * @param callable $onStart An optional callback to invoke immediately when the Reactor starts
 * @return void
 */
function run(callable $onStart = null) {
    reactor()->run($onStart);
}

/**
 * Execute a single event loop iteration
 *
 * @param bool $noWait Should the function return immediately if no watchers are ready to trigger?
 * @return void
 */
function tick($noWait = false) {
    reactor()->tick($noWait);
}

/**
 * Stop the default event reactor and return program flow control
 *
 * @return void
 */
function stop() {
    reactor()->stop();
}

/**
 * Schedule a callback for immediate invocation in the next event loop iteration
 *
 * @param callable $callback A callback to invoke in the next iteration of the event loop
 * @param array $options Watcher options
 * @return string Returns unique (to the process) string watcher ID
 */
function immediately(callable $callback, array $options = []) {
    return reactor()->immediately($callback, $options);
}

/**
 * Schedule a callback to execute once
 *
 * @param callable $callback A callback to invoke after the specified millisecond delay
 * @param int $msDelay the number of milliseconds to wait before invoking $callback
 * @param array $options Watcher options
 * @return string Returns unique (to the process) string watcher ID
 */
function once(callable $callback, $msDelay, array $options = []) {
    return reactor()->once($callback, $msDelay, $options);
}

/**
 * Schedule a recurring callback to execute every $interval seconds until cancelled
 *
 * @param callable $callback A callback to invoke at the $msDelay interval until cancelled
 * @param int $msInterval The interval at which to repeat $callback invocations
 * @param array $options Watcher options
 * @return string Returns unique (to the process) string watcher ID
 */
function repeat(callable $callback, $msInterval, array $options = []) {
    return reactor()->repeat($callback, $msInterval, $options);
}

/**
 * Watch a stream resource for readable data and trigger the callback when actionable
 *
 * @param resource $stream The stream resource to watch for readability
 * @param callable $callback A callback to invoke when the stream reports as readable
 * @param array $options Watcher options
 * @return string Returns unique (to the process) string watcher ID
 */
function onReadable($stream, callable $callback, array $options = []) {
    return reactor()->onReadable($stream, $callback, $options);
}

/**
 * Watch a stream resource to become writable and trigger the callback when actionable
 *
 * @param resource $stream The stream resource to watch for writability
 * @param callable $callback A callback to invoke when the stream reports as writable
 * @param array $options Watcher options
 * @return string Returns unique (to the process) string watcher ID
 */
function onWritable($stream, callable $callback, array $options = []) {
    return reactor()->onWritable($stream, $callback, $options);
}

/**
 * React to process control signals
 *
 * @param int $signo The signal number for which to watch
 * @param callable $callback A callback to invoke when the specified signal is received
 * @param array $options Watcher options
 * @return string Returns unique (to the process) string watcher ID
 */
function onSignal($signo, callable $callback, array $options = []) {
    return reactor()->onSignal($signo, $callback, $options);
}

/**
 * An optional "last-chance" exception handler for errors resulting during callback invocation
 *
 * If an application throws inside the event loop and no onError callback is specified the
 * exception bubbles up and the event loop is stopped. This is undesirable in long-running
 * applications (like servers) where stopping the event loop for an application error is
 * problematic. Amp applications can instead specify the onError callback to handle uncaught
 * exceptions without stopping the event loop.
 *
 * Additionally, generator callbacks which are auto-resolved by the event reactor may fail.
 * Coroutine resolution failures are treated like uncaught exceptions and stop the event reactor
 * if no onError callback is specified to handle these situations.
 *
 * onError callback functions are passed a single parameter: the uncaught exception.
 *
 * @param callable $callback A callback to invoke when an exception occurs inside the event loop
 * @return void
 */
function onError(callable $callback) {
    reactor()->onError($callback);
}

/**
 * Cancel an existing timer/stream watcher
 *
 * @param string $watcherId The watcher ID to be canceled
 * @return void
 */
function cancel($watcherId) {
    reactor()->cancel($watcherId);
}

/**
 * Temporarily disable (but don't cancel) an existing timer/stream watcher
 *
 * @param string $watcherId The watcher ID to be disabled
 * @return void
 */
function disable($watcherId) {
    reactor()->disable($watcherId);
}

/**
 * Enable a disabled timer/stream watcher
 *
 * @param string $watcherId The watcher ID to be enabled
 * @return void
 */
function enable($watcherId) {
    reactor()->enable($watcherId);
}

/**
 * Retrieve an associative array of information about the event reactor
 *
 * The returned array matches the following data describing the reactor's
 * currently registered watchers:
 *
 *  [
 *      "immediately"   => ["enabled" => int, "disabled" => int],
 *      "once"          => ["enabled" => int, "disabled" => int],
 *      "repeat"        => ["enabled" => int, "disabled" => int],
 *      "on_readable"   => ["enabled" => int, "disabled" => int],
 *      "on_writable"   => ["enabled" => int, "disabled" => int],
 *      "on_signal"     => ["enabled" => int, "disabled" => int],
 *      "keep_alive"    => int,
 *      "state"         => int,
 *  ];
 *
 * Reactor implementations may optionally add more information in the return array but
 * at minimum the above key=>value format is always provided.
 *
 * @return array
 */
function info() {
    return reactor()->info();
}

/**
 * Flatten an array of promises into a single promise
 *
 * Upon resolution the returned promise's $result parameter is set to an array
 * whose keys match the original input array and whose values match the individual
 * resolution results of its component promises.
 *
 * If any one of the Promises fails the resulting Promise will immediately fail.
 *
 * @param array $promises An array of promises to flatten into a single promise
 * @return \Amp\Promise
 */
function all(array $promises) {
    if (empty($promises)) {
        return new Success([]);
    }

    $struct = new \StdClass;
    $struct->remaining = \count($promises);
    $struct->results = [];
    $struct->promisor = new Deferred;

    $onResolve = function ($error, $result, $cbData) {
        list($struct, $key) = $cbData;
        if ($struct->remaining <= 0) {
            // If the promisor already resolved we don't need to bother
            return;
        }
        if ($error) {
            $struct->results = null;
            $struct->remaining = 0;
            $struct->promisor->fail($error);
            return;
        }

        $struct->results[$key] = $result;
        if (--$struct->remaining === 0) {
            $struct->promisor->succeed($struct->results);
        }
    };

    foreach ($promises as $key => $promise) {
        if ($promise instanceof Promise) {
            $promise->when($onResolve, [$struct, $key]);
        } else {
            $struct->results[$key] = $promise;
            if (--$struct->remaining === 0) {
                $struct->promisor->succeed($struct->results);
            }
        }
    }

    return $struct->promisor->promise();
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
 * @param array $promises An array of promises to flatten into a single promise
 * @return \Amp\Promise
 */
function some(array $promises) {
    if (empty($promises)) {
        return new Failure(new \LogicException(
            "No promises or values provided for resolution"
        ));
    }

    $struct = new \StdClass;
    $struct->remaining = \count($promises);
    $struct->errors = [];
    $struct->results = [];
    $struct->promisor = new Deferred;

    $onResolve = function ($error, $result, $cbData) {
        list($struct, $key) = $cbData;
        if ($error) {
            $struct->errors[$key] = $error;
        } else {
            $struct->results[$key] = $result;
        }
        if (--$struct->remaining === 0) {
            if (empty($struct->results)) {
                $struct->promisor->fail(new CombinatorException(
                    "All promises passed to Amp\\some() failed", $struct->errors
                ));
            } else {
                $struct->promisor->succeed([$struct->errors, $struct->results]);
            }
        }
    };

    foreach ($promises as $key => $promise) {
        if ($promise instanceof Promise) {
            $promise->when($onResolve, [$struct, $key]);
        } else {
            $struct->results[$key] = $promise;
            if (--$struct->remaining === 0) {
                $struct->promisor->succeed([$struct->errors, $struct->results]);
            }
        }
    }

    return $struct->promisor->promise();
}

/**
 * Resolves with a two-item array delineating successful and failed Promise results.
 *
 * This function is the same as some() with the notable exception that it will never fail even
 * if all promises in the array resolve unsuccessfully.
 *
 * @param array $promises An array of promises to flatten into a single promise
 * @return \Amp\Promise
 */
function any(array $promises) {
    if (empty($promises)) {
        return new Success([[], []]);
    }

    $struct = new \StdClass;
    $struct->remaining = \count($promises);
    $struct->errors = [];
    $struct->results = [];
    $struct->promisor = new Deferred;

    $onResolve = function ($error, $result, $cbData) {
        list($struct, $key) = $cbData;
        if ($error) {
            $struct->errors[$key] = $error;
        } else {
            $struct->results[$key] = $result;
        }
        if (--$struct->remaining === 0) {
            $struct->promisor->succeed([$struct->errors, $struct->results]);
        }
    };

    foreach ($promises as $key => $promise) {
        if ($promise instanceof Promise) {
            $promise->when($onResolve, [$struct, $key]);
        } else {
            $struct->results[$key] = $promise;
            if (--$struct->remaining === 0) {
                $struct->promisor->succeed([$struct->errors, $struct->results]);
            }
        }
    }

    return $struct->promisor->promise();
}

/**
 * Resolves with the first successful Promise value. The resulting Promise will only fail if all
 * Promise values in the group fail or if the initial Promise array is empty.
 *
 * @param array $promises An array of promises to flatten into a single promise
 * @return \Amp\Promise
 */
function first(array $promises) {
    if (empty($promises)) {
        return new Failure(new \LogicException(
            "No promises or values provided"
        ));
    }

    $struct = new \StdClass;
    $struct->errors = [];
    $struct->remaining = \count($promises);
    $struct->promisor = new Deferred;

    $onResolve = function ($error, $result, $cbData) {
        list($struct, $key) = $cbData;
        if ($struct->remaining === 0) {
            return;
        }
        if ($error) {
            $struct->errors[$key] = $error;
        } else {
            $struct->remaining = 0;
            $struct->promisor->succeed($result);
            return;
        }
        if (--$struct->remaining === 0) {
            $struct->promisor->fail(new CombinatorException(
                "All promises failed", $struct->errors
            ));
        }
    };

    foreach ($promises as $key => $promise) {
        if ($promise instanceof Promise) {
            $promise->when($onResolve, [$struct, $key]);
        } else {
            $struct->remaining = 0;
            $struct->promisor->succeed($promise);
            break;
        }
    }

    return $struct->promisor->promise();
}

/**
 * Map promised deferred values using the specified functor.
 *
 * @param array $promises An array of promises whose values -- once resolved -- will be mapped by the functor
 * @param callable $functor The mapping function to apply to eventual promise results
 * @return \Amp\Promise
 */
function map(array $promises, callable $functor) {
    if (empty($promises)) {
        return new Success([]);
    }

    $struct = new \StdClass;
    $struct->remaining = \count($promises);
    $struct->results = [];
    $struct->promisor = new Deferred;
    $struct->functor = $functor;

    $onResolve = function ($error, $result, $cbData) {
        list($struct, $key) = $cbData;
        if ($struct->remaining <= 0) {
            // If the promisor already resolved we don't need to bother
            return;
        }
        if ($error) {
            $struct->results = null;
            $struct->remaining = 0;
            $struct->promisor->fail($error);
            return;
        }
        $struct->remaining--;
        try {
            $struct->results[$key] = \call_user_func($struct->functor, $result);
            if ($struct->remaining === 0) {
                $struct->promisor->succeed($struct->results);
            }
        } catch (\Throwable $e) {
            // @TODO Remove coverage ignore block once PHP5 support is no longer required
            // @codeCoverageIgnoreStart
            $struct->remaining = 0;
            $struct->promisor->fail($e);
            // @codeCoverageIgnoreEnd
        } catch (\Exception $e) {
            // @TODO Remove this catch block once PHP5 support is no longer required
            $struct->remaining = 0;
            $struct->promisor->fail($e);
        }
    };

    foreach ($promises as $key => $promise) {
        if ($promise instanceof Promise) {
            $promise->when($onResolve, [$struct, $key]);
        } else {
            $struct->remaining--;
            try {
                $struct->results[$key] = \call_user_func($struct->functor, $promise);
            } catch (\Throwable $e) {
                // @TODO Remove coverage ignore block once PHP5 support is no longer required
                // @codeCoverageIgnoreStart
                $struct->remaining = 0;
                $struct->promisor->fail($e);
                break;
                // @codeCoverageIgnoreEnd
            } catch (\Exception $e) {
                // @TODO Remove this catch block once PHP5 support is no longer required
                $struct->remaining = 0;
                $struct->promisor->fail($e);
                break;
            }
        }
    }

    return $struct->promisor->promise();
}

/**
 * Filter deferred values using the specified functor.
 *
 * If the functor returns a truthy value the resolved promise result is retained, otherwise it is
 * discarded. Array keys are retained for any results not filtered out by the functor.
 *
 * @param array $promises An array of promises whose values -- once resolved -- will be filtered by the functor
 * @param callable $functor The filtering function to apply to eventual promise results
 * @return \Amp\Promise
 */
function filter(array $promises, callable $functor = null) {
    if (empty($promises)) {
        return new Success([]);
    }

    if (empty($functor)) {
        $functor = function ($r) {
            return (bool) $r;
        };
    }

    $struct = new \StdClass;
    $struct->remaining = \count($promises);
    $struct->results = [];
    $struct->promisor = new Deferred;
    $struct->functor = $functor;

    $onResolve = function ($error, $result, $cbData) {
        list($struct, $key) = $cbData;
        if ($struct->remaining <= 0) {
            // If the promisor already resolved we don't need to bother
            return;
        }
        if ($error) {
            $struct->results = null;
            $struct->remaining = 0;
            $struct->promisor->fail($error);
            return;
        }
        $struct->remaining--;
        try {
            if (\call_user_func($struct->functor, $result)) {
                $struct->results[$key] = $result;
            }
            if ($struct->remaining === 0) {
                $struct->promisor->succeed($struct->results);
            }
        } catch (\Throwable $e) {
            // @TODO Remove coverage ignore block once PHP5 support is no longer required
            // @codeCoverageIgnoreStart
            $struct->remaining = 0;
            $struct->promisor->fail($e);
            // @codeCoverageIgnoreEnd
        } catch (\Exception $e) {
            // @TODO Remove this catch block once PHP5 support is no longer required
            $struct->remaining = 0;
            $struct->promisor->fail($e);
        }
    };

    foreach ($promises as $key => $promise) {
        if ($promise instanceof Promise) {
            $promise->when($onResolve, [$struct, $key]);
        } else {
            $struct->remaining--;
            try {
                if (\call_user_func($struct->functor, $promise)) {
                    $struct->results[$key] = $promise;
                }
                if ($struct->remaining === 0) {
                    $struct->promisor->succeed($struct->results);
                    break;
                }
            } catch (\Throwable $e) {
                // @TODO Remove coverage ignore block once PHP5 support is no longer required
                // @codeCoverageIgnoreStart
                $struct->remaining = 0;
                $struct->promisor->fail($e);
                break;
                // @codeCoverageIgnoreEnd
            } catch (\Exception $e) {
                // @TODO Remove this catch block once PHP5 support is no longer required
                $struct->remaining = 0;
                $struct->promisor->fail($e);
                break;
            }
        }
    }

    return $struct->promisor->promise();
}

/**
 * Pipe the promised value through the specified functor once it resolves.
 *
 * @param mixed $promise Any value is acceptable -- non-promises are normalized to promise form
 * @param callable $functor The functor through which to pipe the resolved promise value
 * @return \Amp\Promise
 */
function pipe($promise, callable $functor) {
    if (!$promise instanceof Promise) {
        try {
            return new Success(\call_user_func($functor, $promise));
        } catch (\Throwable $e) {
            // @TODO Remove coverage ignore block once PHP5 support is no longer required
            // @codeCoverageIgnoreStart
            return new Failure($e);
            // @codeCoverageIgnoreEnd
        } catch (\Exception $e) {
            // @TODO Remove this catch block once PHP5 support is no longer required
            return new Failure($e);
        }
    }

    $promisor = new Deferred;
    $promise->when(function ($error, $result) use ($promisor, $functor) {
        if ($error) {
            $promisor->fail($error);
            return;
        }
        try {
            $promisor->succeed(\call_user_func($functor, $result));
        } catch (\Throwable $error) {
            // @TODO Remove coverage ignore block once PHP5 support is no longer required
            // @codeCoverageIgnoreStart
            $promisor->fail($error);
            // @codeCoverageIgnoreEnd
        } catch (\Exception $error) {
            // @TODO Remove this catch block once PHP5 support is no longer required
            $promisor->fail($error);
        }
    });

    return $promisor->promise();
}

/**
 * Normalize an array of mixed values/Promises/Promisors to array<Promise>
 *
 * @param array $values
 * @return array Returns an array of Promise instances
 */
function promises(array $values) {
    foreach ($values as $key => $value) {
        if ($value instanceof Promise) {
            continue;
        } elseif ($value instanceof Promisor) {
            $values[$key] = $value->promise();
        } else {
            $values[$key] = new Success($value);
        }
    }

    return $values;
}

/**
 * Create an artificial timeout for any Promise instance
 *
 * If the timeout expires prior to promise resolution the returned
 * promise is failed.
 *
 * @param \Amp\Promise $promise The promise to which the timeout applies
 * @param int $msTimeout The timeout in milliseconds
 * @return \Amp\Promise
 */
function timeout(Promise $promise, $msTimeout) {
    $resolved = false;
    $promisor = new Deferred;
    $watcherId = once(function () use ($promisor, &$resolved) {
        $resolved = true;
        $promisor->fail(new TimeoutException(
            "Promise resolution timed out"
        ));
    }, $msTimeout);
    $promise->when(function ($error = null, $result = null) use ($promisor, $watcherId, &$resolved) {
        if ($resolved) {
            return;
        }
        $resolved = true;
        cancel($watcherId);
        if ($error) {
            $promisor->fail($error);
        } else {
            $promisor->succeed($result);
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
 * @param \Amp\Promise $promise The promise on which to wait
 * @throws \Exception if the promise fails
 * @return mixed Returns the eventual resolution result for the specified promise
 */
function wait(Promise $promise) {
    $isWaiting = true;
    $resolvedError = null;
    $resolvedResult = null;

    $promise->when(function ($error, $result) use (&$isWaiting, &$resolvedError, &$resolvedResult) {
        $isWaiting = false;
        $resolvedError = $error;
        $resolvedResult = $result;
    });

    while ($isWaiting) {
        tick();
    }

    if ($resolvedError) {
        /** @var $resolvedError \Throwable|\Exception */
        throw $resolvedError;
    }

    return $resolvedResult;
}

/**
 * Return a new function that will be resolved as a coroutine when invoked
 *
 * @param callable $func The callable to be wrapped for coroutine resolution
 * @return callable Returns a wrapped callable
 * @TODO Use variadic function instead of func_get_args() once PHP5.5 is no longer supported
 */
function coroutine(callable $func) {
    return function () use ($func) {
        $out = \call_user_func_array($func, \func_get_args());
        if ($out instanceof \Generator) {
            return resolve($out);
        } elseif ($out instanceof Promise) {
            return $out;
        } else {
            return new Success($out);
        }
    };
}

/**
 * Resolve a Generator coroutine function
 *
 * Upon resolution the Generator return value is used to succeed the promised result. If an
 * error occurs during coroutine resolution the returned promise fails.
 *
 * @param \Generator|callable $generator A generator or callable that returns a generator to resolve as a coroutine
 * @return \Amp\Promise
 */
function resolve($generator) {
    if (!$generator instanceof \Generator) {
        if (!\is_callable($generator)) {
            throw new \InvalidArgumentException("Coroutine to resolve must be callable or instance of Generator");
        }

        $generator = \call_user_func($generator);

        if (!$generator instanceof \Generator) {
            throw new \LogicException("Callable passed to resolve() did not return an instance of Generator");
        }
    }

    $cs = new CoroutineState;
    $cs->promisor = new Deferred;
    $cs->generator = $generator;
    $cs->returnValue = null;
    $cs->currentPromise = null;
    $cs->nestingLevel = 0;
    $cs->reactor = reactor();

    __coroutineAdvance($cs);

    return $cs->promisor->promise();
}

/**
 * This function is used internally when resolving coroutines.
 * It is not considered part of the public API and library users
 * should not rely upon it in applications.
 */
function __coroutineAdvance(CoroutineState $cs) {
    try {
        $yielded = $cs->generator->current();
        if (!isset($yielded)) {
            if ($cs->generator->valid()) {
                $cs->reactor->immediately('Amp\__coroutineNextTick', ["cb_data" => $cs]);
            } elseif (isset($cs->returnValue)) {
                $cs->promisor->succeed($cs->returnValue);
            } else {
                $result = (PHP_MAJOR_VERSION >= 7) ? $cs->generator->getReturn() : null;
                $cs->promisor->succeed($result);
            }
        } elseif ($yielded instanceof Promise) {
            if ($cs->nestingLevel < 3) {
                $cs->nestingLevel++;
                $yielded->when('Amp\__coroutineSend', $cs);
                $cs->nestingLevel--;
            } else {
                $cs->currentPromise = $yielded;
                $cs->reactor->immediately('Amp\__coroutineNextTick', ["cb_data" => $cs]);
            }
        } elseif ($yielded instanceof CoroutineResult) {
            /**
             * @TODO This block is necessary for PHP5; remove once PHP7 is required and
             *       we have return expressions in generators
             */
            $cs->returnValue = $yielded->getReturn();
            __coroutineSend(null, null, $cs);
        } else {
            /**
             * @TODO Remove CoroutineResult from error message once PHP7 is required
             */
            $error = new \DomainException(makeGeneratorError($cs->generator, \sprintf(
                "Unexpected yield (Promise|CoroutineResult|null expected); %s yielded at key %s",
                \is_object($yielded) ? \get_class($yielded) : \gettype($yielded),
                $cs->generator->key()
            )));
            $cs->reactor->immediately(function () use ($cs, $error) {
                $cs->promisor->fail($error);
            });
        }
    } catch (\Throwable $uncaught) {
        /**
         * @codeCoverageIgnoreStart
         * @TODO Remove these coverage ignore lines once PHP7 is required
         */
        $cs->reactor->immediately(function () use ($cs, $uncaught) {
            $cs->promisor->fail($uncaught);
        });
        /**
         * @codeCoverageIgnoreEnd
         */
    } catch (\Exception $uncaught) {
        /**
         * @TODO This extra catch block is necessary for PHP5; remove once PHP7 is required
         */
        $cs->reactor->immediately(function () use ($cs, $uncaught) {
            $cs->promisor->fail($uncaught);
        });
    }
}

/**
 * This function is used internally when resolving coroutines.
 * It is not considered part of the public API and library users
 * should not rely upon it in applications.
 */
function __coroutineNextTick($watcherId, CoroutineState $cs) {
    if ($cs->currentPromise) {
        $promise = $cs->currentPromise;
        $cs->currentPromise = null;
        $promise->when('Amp\__coroutineSend', $cs);
    } else {
        __coroutineSend(null, null, $cs);
    }
}

/**
 * This function is used internally when resolving coroutines.
 * It is not considered part of the public API and library users
 * should not rely upon it in applications.
 */
function __coroutineSend($error, $result, CoroutineState $cs) {
    try {
        if ($error) {
            $cs->generator->throw($error);
        } else {
            $cs->generator->send($result);
        }
        __coroutineAdvance($cs);
    } catch (\Throwable $uncaught) {
        /**
         * @codeCoverageIgnoreStart
         * @TODO Remove these coverage ignore lines once PHP7 is required
         */
        $cs->reactor->immediately(function () use ($cs, $uncaught) {
            $cs->promisor->fail($uncaught);
        });
        /**
         * @codeCoverageIgnoreEnd
         */
    } catch (\Exception $uncaught) {
        /**
         * @TODO This extra catch block is necessary for PHP5; remove once PHP7 is required
         */
        $cs->reactor->immediately(function () use ($cs, $uncaught) {
            $cs->promisor->fail($uncaught);
        });
    }
}

/**
 * A general purpose function for creating error messages from generator yields
 *
 * @param \Generator $generator
 * @param string $prefix
 * @return string
 */
function makeGeneratorError(\Generator $generator, $prefix = "Generator error") {
    if (PHP_MAJOR_VERSION < 7 || !$generator->valid()) {
        return $prefix;
    }

    $reflGen = new \ReflectionGenerator($generator);
    $exeGen = $reflGen->getExecutingGenerator();
    if ($isSubgenerator = ($exeGen !== $generator)) {
        $reflGen = new \ReflectionGenerator($exeGen);
    }

    return sprintf(
        "{$prefix} on line %s in %s",
        $reflGen->getExecutingLine(),
        $reflGen->getExecutingFile()
    );
}
