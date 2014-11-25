<?php

namespace Amp;

/**
 * Get a singleton event reactor instance
 *
 * Note that the $factory callable is only invoked if no global reactor has yet been initialized.
 *
 * @param callable $factory Optional factory callable for initializing a reactor
 * @return \Amp\Reactor
 */
function reactor(callable $factory = null) {
    static $reactor;
    return ($reactor = $reactor ?: ReactorFactory::select($factory));
}

/**
 * If any one of the Promises fails the resulting Promise will fail. Otherwise
 * the resulting Promise succeeds with an array matching keys from the input array
 * to their resolved values.
 *
 * @param \Amp\Reactor $reactor
 * @param array[\Amp\Promise] $promises
 * @return \Amp\Promise
 */
function all(Reactor $reactor, array $promises) {
    if (empty($promises)) {
        return new Success([]);
    }

    $results    = [];
    $remaining  = count($promises);
    $promisor   = new Future($reactor);
    $isResolved = false;

    foreach ($promises as $key => $resolvable) {
        if (!$resolvable instanceof Promise) {
            $results[$key] = $resolvable;
            $remaining--;
            continue;
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
 * @param \Amp\Reactor $reactor
 * @param array[\Amp\Promise] $promises
 * @return \Amp\Promise
 */
function some(Reactor $reactor, array $promises) {
    if (empty($promises)) {
        return new Failure(new \LogicException(
            'No promises or values provided for resolution'
        ));
    }

    $results   = $errors = [];
    $remaining = count($promises);
    $promisor  = new Future($reactor);

    foreach ($promises as $key => $resolvable) {
        if (!$resolvable instanceof Promise) {
            $results[$key] = $resolvable;
            $remaining--;
            continue;
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
 * @param \Amp\Reactor $reactor
 * @param array $promises
 * @return \Amp\Promise
 */
function any(Reactor $reactor, array $promises) {
    if (empty($promises)) {
        return new Success([], []);
    }

    $results   = [];
    $errors    = [];
    $remaining = count($promises);
    $promisor  = new Future($reactor);

    foreach ($promises as $key => $resolvable) {
        if (!$resolvable instanceof Promise) {
            $results[$key] = $resolvable;
            $remaining--;
            continue;
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
 * @param \Amp\Reactor $reactor
 * @param array[\Amp\Promise] $promises
 * @return \Amp\Promise
 */
function first(Reactor $reactor, array $promises) {
    if (empty($promises)) {
        return new Failure(new \LogicException(
            'No promises or values provided for resolution'
        ));
    }

    $remaining  = count($promises);
    $isComplete = false;
    $promisor   = new Future($reactor);

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
 * @param \Amp\Reactor $reactor
 * @param array $promises
 * @param callable $functor
 * @return \Amp\Promise
 */
function map(Reactor $reactor, array $promises, callable $functor) {
    if (empty($promises)) {
        return new Success([]);
    }

    $results   = [];
    $remaining = count($promises);
    $promisor  = new Future($reactor);

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
 * @param \Amp\Reactor $reactor
 * @param array $promises
 * @param callable $functor
 * @return \Amp\Promise
 */
function filter(Reactor $reactor, array $promises, callable $functor) {
    if (empty($promises)) {
        return new Success([]);
    }

    $results   = [];
    $remaining = count($promises);
    $promisor  = new Future($reactor);

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
 * A co-routine to resolve Generators
 *
 * Returns a promise that will resolve when the generator completes. The final value yielded by
 * the generator is used to resolve the returned Promise on success.
 *
 * Example:
 *
 * function anotherGenerator() {
 *     yield 1;
 * }
 *
 * $generator = function() {
 *     $a = (yield 2);
 *     $b = (yield new Success(21));
 *     $c = (yield anotherGenerator());
 *     yield $a * $b * $c;
 * };
 *
 * $reactor = new Amp\NativeReactor;
 * $result = resolve($reactor, $generator())->wait();
 * var_dump($result); // int(42)
 *
 * @param \Amp\Reactor $reactor
 * @param \Generator $gen
 * @return \Amp\Promise
 */
function resolve(Reactor $reactor, \Generator $gen) {
    $promisor = new Future($reactor);
    __advanceGenerator($reactor, $gen, $promisor);

    return $promisor;
}

function __advanceGenerator(Reactor $reactor, \Generator $gen, Promisor $promisor, $previous = null) {
    try {
        if ($gen->valid()) {
            $key = $gen->key();
            $current = $gen->current();
            $promiseStruct = __promisifyGeneratorYield($reactor, $key, $current);
            $reactor->immediately(function() use ($reactor, $gen, $promisor, $promiseStruct) {
                list($promise, $noWait) = $promiseStruct;
                if ($noWait) {
                    __sendToGenerator($reactor, $gen, $promisor, $error = null, $result = null);
                } else {
                    $promise->when(function($error, $result) use ($reactor, $gen, $promisor) {
                        __sendToGenerator($reactor, $gen, $promisor, $error, $result);
                    });
                }
            });
        } else {
            $promisor->succeed($previous);
        }
    } catch (\Exception $error) {
        $promisor->fail($error);
    }
}

function __promisifyGeneratorYield(Reactor $reactor, $key, $current) {
    $noWait = false;

    if ($key === (string) $key) {
        goto explicit_key;
    } else {
        goto implicit_key;
    }

    explicit_key: {
        $key = strtolower($key);
        if ($key[0] === YieldCommands::NOWAIT_PREFIX) {
            $noWait = true;
            $key = substr($key, 1);
        }

        switch ($key) {
            case YieldCommands::ALL:
                // fallthrough
            case YieldCommands::ANY:
                // fallthrough
            case YieldCommands::SOME:
                if (is_array($current)) {
                    goto combinator;
                } else {
                    $promise = new Failure(new \DomainException(
                        sprintf('"%s" yield command expects array; %s yielded', $key, gettype($current))
                    ));
                    goto return_struct;
                }
            case YieldCommands::WAIT:
                goto wait;
            case YieldCommands::IMMEDIATELY:
                goto immediately;
            case YieldCommands::ONCE:
                // fallthrough
            case YieldCommands::REPEAT:
                goto schedule;
            case YieldCommands::ON_READABLE:
                $ioWatchMethod = 'onReadable';
                goto stream_io_watcher;
            case YieldCommands::ON_WRITABLE:
                $ioWatchMethod = 'onWritable';
                goto stream_io_watcher;
            case YieldCommands::ENABLE:
                // fallthrough
            case YieldCommands::DISABLE:
                // fallthrough
            case YieldCommands::CANCEL:
                goto watcher_control;
            case YieldCommands::NOWAIT:
                $noWait = true;
                goto implicit_key;
            default:
                $promise = new Failure(new \DomainException(
                    sprintf('Unknown or invalid yield key: "%s"', $key)
                ));
                goto return_struct;
        }
    }

    implicit_key: {
        if ($current instanceof Promise) {
            $promise = $current;
        } elseif ($current instanceof \Generator) {
            $promise = resolve($reactor, $current);
        } elseif (is_array($current)) {
            $key = YieldCommands::ALL;
            goto combinator;
        } else {
            $promise = new Success($current);
        }

        goto return_struct;
    }

    combinator: {
        $promises = [];
        foreach ($current as $index => $element) {
            if ($element instanceof Promise) {
                $promise = $element;
            } elseif ($element instanceof \Generator) {
                $promise = resolve($reactor, $element);
            } else {
                $promise = new Success($element);
            }

            $promises[$index] = $promise;
        }

        $combinatorFunction = __NAMESPACE__ . "\\{$key}";
        $promise = $combinatorFunction($reactor, $promises);

        goto return_struct;
    }

    immediately: {
        if (is_callable($current)) {
            $watcherId = $reactor->immediately($current);
            $promise = new Success($watcherId);
        } else {
            $promise = new Failure(new \DomainException(
                sprintf(
                    '"%s" yield command requires callable; %s provided',
                    $key,
                    gettype($current)
                )
            ));
        }

        goto return_struct;
    }

    schedule: {
        if (is_array($current) && isset($current[0], $current[1]) && is_callable($current[0])) {
            list($func, $msDelay) = $current;
            $watcherId = $reactor->{$key}($func, $msDelay);
            $promise = new Success($watcherId);
        } else {
            $promise = new Failure(new \DomainException(
                sprintf(
                    '"%s" yield command requires [callable $func, int $msDelay]; %s provided',
                    $key,
                    gettype($current)
                )
            ));
        }

        goto return_struct;
    }

    stream_io_watcher: {
        if (is_array($current) && isset($current[0], $current[1]) && is_callable($current[1])) {
            list($stream, $func, $enableNow) = $current;
            $watcherId = $reactor->{$ioWatchMethod}($stream, $func, $enableNow);
            $promise = new Success($watcherId);
        } else {
            $promise = new Failure(new \DomainException(
                sprintf(
                    '"%s" yield command requires [resource $stream, callable $func, bool $enableNow]; %s provided',
                    $key,
                    gettype($current)
                )
            ));
        }

        goto return_struct;
    }

    wait: {
        $promisor = new Future($reactor);
        $reactor->once(function() use ($promisor) {
            $promisor->succeed();
        }, (int) $current);

        $promise = $promisor;

        goto return_struct;
    }

    watcher_control: {
        $reactor->{$key}($current);
        $promise = new Success;

        goto return_struct;
    }

    return_struct: {
        return [$promise, $noWait];
    }
}

function __sendToGenerator(Reactor $reactor, \Generator $gen, Promisor $promisor, \Exception $error = null, $result = null) {
    try {
        if ($error) {
            $gen->throw($error);
        } else {
            $gen->send($result);
        }
        __advanceGenerator($reactor, $gen, $promisor, $result);
    } catch (\Exception $error) {
        $promisor->fail($error);
    }
}
