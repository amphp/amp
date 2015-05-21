<?php

namespace Amp;

/**
 * Get the default event reactor instance
 *
 * @param \Amp\Reactor $assignReactor Optionally specify a new default event reactor instance
 * @return \Amp\Reactor Returns the default reactor instance
 */
function reactor(Reactor $assignReactor = null): Reactor {
    static $reactor;
    if ($assignReactor) {
        return ($reactor = $assignReactor);
    } elseif ($reactor) {
        return $reactor;
    } elseif (\extension_loaded('uv')) {
        return ($reactor = new UvReactor);
    } else {
        return ($reactor = new NativeReactor);
    }
}

/**
 * Start the default event reactor and assume program flow control
 *
 * This is a shortcut function for invoking Reactor::run() on the global
 * default event reactor.
 *
 * @param callable $onStart An optional callback to invoke immediately when the Reactor starts
 * @return void
 */
function run(callable $onStart = null) {
    reactor()->run($onStart);
}

/**
 * Stop the default event reactor and return program flow control
 *
 * This is a shortcut function for invoking Reactor::stop() on the global
 * default event reactor.
 *
 * @return void
 */
function stop() {
    reactor()->stop();
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
 * @param array An array of promises to flatten into a single promise
 * @return \Amp\Promise
 */
function all(array $promises): Promise {
    if (empty($promises)) {
        return new Success([]);
    }

    $struct = new \StdClass;
    $struct->remaining = count($promises);
    $struct->results = [];
    $struct->promisor = new Deferred;

    $onResolve = function($error, $result, $cbData) {
        list($struct, $key) = $cbData;
        if (empty($struct->remaining)) {
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
 * @param array An array of promises to flatten into a single promise
 * @return \Amp\Promise
 */
function some(array $promises): Promise {
    if (empty($promises)) {
        return new Failure(new \LogicException(
            "No promises or values provided for resolution"
        ));
    }

    $struct = new \StdClass;
    $struct->remaining = count($promises);
    $struct->errors = [];
    $struct->results = [];
    $struct->promisor = new Deferred;

    $onResolve = function($error, $result, $cbData) {
        list($struct, $key) = $cbData;
        if ($error) {
            $struct->errors[$key] = $error;
        } else {
            $struct->results[$key] = $result;
        }
        if (--$struct->remaining) {
            return;
        }
        if (empty($struct->results)) {
            array_unshift($struct->errors, "All promises passed to Amp\some() failed");
            $struct->promisor->fail(new \RuntimeException(
                implode("\n\n", $struct->errors)
            ));
        } else {
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
 * Resolves with a two-item array delineating successful and failed Promise results.
 *
 * This function is the same as some() with the notable exception that it will never fail even
 * if all promises in the array resolve unsuccessfully.
 *
 * @param array An array of promises to flatten into a single promise
 * @return \Amp\Promise
 */
function any(array $promises): Promise {
    if (empty($promises)) {
        return new Success([[], []]);
    }

    $struct = new \StdClass;
    $struct->remaining = count($promises);
    $struct->errors = [];
    $struct->results = [];
    $struct->promisor = new Deferred;

    $onResolve = function($error, $result, $cbData) {
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
 * @param array An array of promises to flatten into a single promise
 * @return \Amp\Promise
 */
function first(array $promises): Promise {
    if (empty($promises)) {
        return new Failure(new \LogicException(
            "No promises or values provided for first() resolution"
        ));
    }

    $struct = new \StdClass;
    $struct->remaining = count($promises);
    $struct->promisor = new Deferred;

    $onResolve = function($error, $result, $cbData) {
        list($struct, $key) = $cbData;
        if (empty($struct->remaining)) {
            return;
        }
        if (empty($error)) {
            $struct->remaining = 0;
            $struct->promisor->succeed($result);
            return;
        }
        if (--$struct->remaining === 0) {
            $struct->promisor->fail(new \RuntimeException(
                "All promises passed for first() resolution failed"
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
 * Map promised deferred values using the specified functor
 *
 * @param array An array of promises whose values -- once resoved -- will be mapped by the functor
 * @param callable $functor The mapping function to apply to eventual promise results
 * @return \Amp\Promise
 */
function map(array $promises, callable $functor): Promise {
    if (empty($promises)) {
        return new Success([]);
    }

    $struct = new \StdClass;
    $struct->remaining = count($promises);
    $struct->results = [];
    $struct->promisor = new Deferred;
    $struct->functor = $functor;

    $onResolve = function($error, $result, $cbData) {
        list($struct, $key) = $cbData;
        if (empty($struct->remaining)) {
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
            $struct->results[$key] = call_user_func($struct->functor, $result);
        } catch (\Exception $e) {
            $struct->remaining = 0;
            $struct->promisor->fail($e);
            return;
        }
        if ($struct->remaining === 0) {
            $struct->promisor->succeed($struct->results);
        }
    };

    foreach ($promises as $key => $promise) {
        if ($promise instanceof Promise) {
            $promise->when($onResolve, [$struct, $key]);
        } else {
            $struct->remaining--;
            try {
                $struct->results[$key] = call_user_func($struct->functor, $promise);
            } catch (\Exception $e) {
                $struct->remaining = 0;
                $struct->promisor->fail($e);
            }
            if ($struct->remaining === 0) {
                break;
            }
        }
    }

    return $struct->promisor->promise();
}

/**
 * Filter deferred values using the specified functor
 *
 * If the functor returns a truthy value the resolved promise result is retained, otherwise it is
 * discarded. Array keys are retained for any results not filtered out by the functor.
 *
 * @param array An array of promises whose values -- once resoved -- will be filtered by the functor
 * @param callable $functor The filtering function to apply to eventual promise results
 * @return \Amp\Promise
 */
function filter(array $promises, callable $functor): Promise {
    if (empty($promises)) {
        return new Success([]);
    }

    $struct = new \StdClass;
    $struct->remaining = count($promises);
    $struct->results = [];
    $struct->promisor = new Deferred;
    $struct->functor = $functor;

    $onResolve = function($error, $result, $cbData) {
        list($struct, $key) = $cbData;
        if (empty($struct->remaining)) {
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
            if (call_user_func($struct->functor, $result)) {
                $struct->results[$key] = $result;
            }
        } catch (\Exception $e) {
            $struct->remaining = 0;
            $struct->promisor->fail($e);
            return;
        }
        if ($struct->remaining === 0) {
            $struct->promisor->succeed($struct->results);
        }
    };

    foreach ($promises as $key => $promise) {
        if ($promise instanceof Promise) {
            $promise->when($onResolve, [$struct, $key]);
        } else {
            $struct->remaining--;
            try {
                if (call_user_func($struct->functor, $promise)) {
                    $struct->results[$key] = $promise;
                }
            } catch (\Exception $e) {
                $struct->remaining = 0;
                $struct->promisor->fail($e);
            }
            if ($struct->remaining === 0) {
                break;
            }
        }
    }

    return $struct->promisor->promise();
}

/**
 * Pipe the promised value through the specified functor once it resolves
 *
 * @param mixed $promise Any value is acceptable -- non-promises are normalized to promise form
 * @param callable $functor The functor through which to pipe the resolved promise value
 * @return \Amp\Promise
 */
function pipe($promise, callable $functor): Promise {
    if (!($promise instanceof Promise)) {
        try {
            return new Success($functor($promise));
        } catch (\Exception $e) {
            return new Failure($e);
        }
    }

    $promisor = new Deferred;
    $promise->when(function($error, $result) use ($promisor, $functor) {
        if ($error) {
            $promisor->fail($error);
            return;
        }
        try {
            $promisor->succeed($functor($result));
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
 * @param \Amp\Promise $promise The promise on which to wait
 * @param \Amp\Reactor $reactor
 * @throws \Exception if the promise fails
 * @return mixed Returns the eventual resolution result for the specified promise
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

    $reactor = $reactor ?: reactor();
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
 *
 * @param callable $func The callable to be wrapped for coroutine resolution
 * @param \Amp\Reactor $reactor
 * @param callable $promisifier
 * @return callable Returns the wrapped callable
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
 *
 * @param \Generator $generator The generator to resolve as a coroutine
 * @param \Amp\Reactor $reactor
 * @param callable $promisifier
 */
function resolve(\Generator $generator, Reactor $reactor = null, callable $promisifier = null): Promise {
    $cs = new \StdClass;
    $cs->reactor = $reactor ?: reactor();
    $cs->promisor = new Deferred;
    $cs->generator = $generator;
    $cs->promisifier = $promisifier;
    $cs->returnValue = null;
    $cs->currentPromise = null;
    __coroutineAdvance($cs);

    return $cs->promisor->promise();
}

function __coroutineAdvance($cs) {
    try {
        if (!$cs->generator->valid()) {
            $cs->promisor->succeed($cs->returnValue ?? $cs->generator->getReturn());
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
            __coroutineCustomPromisify($cs, $key, $yielded);
        } else {
            $promisor = $cs->promisor;
            $cs->promisor = null;
            $promisor->fail(new \DomainException(
                __coroutineYieldError($cs->generator, $key, $yielded)
            ));
            return;
        }
        $cs->reactor->immediately("Amp\__coroutineNextTick", ["cb_data" => $cs]);
    } catch (\Exception $uncaught) {
        if ($promisor = $cs->promisor) {
            $cs->promisor = null;
            $promisor->fail($uncaught);
        }
    }
}

function __coroutineCustomPromisify($cs, $key, $yielded) {
    $promise = ($cs->promisifier)($cs->generator, $key, $yielded);
    if ($promise instanceof Promise) {
        $cs->currentPromise = $promise;
    } else {
        throw new \DomainException(sprintf(
            "Invalid promisifier yield of type %s; Promise|null expected",
            is_object($promise) ? get_class($promise) : gettype($promise)
        ));
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
        if ($promisor = $cs->promisor) {
            $cs->promisor = null;
            $promisor->fail($uncaught);
        }
    }
}

function __coroutineYieldError(\Generator $generator, $key, $yielded): string {
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
