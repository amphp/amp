<?php

namespace Amp;

abstract class CoroutineResolver implements Reactor {
    /**
     * Resolve the specified generator
     *
     * Upon resolution the final yielded value is used to succeed the returned promise. If an
     * error occurs the returned promise is failed appropriately.
     *
     * @param \Generator $gen
     * @return Promise
     */
    public function coroutine(\Generator $gen) {
        $promisor = new Future;
        $this->advanceGenerator($gen, $promisor, null);

        return $promisor;
    }

    private function advanceGenerator(\Generator $gen, Promisor $promisor, $return) {
        try {
            if (!$gen->valid()) {
                $promisor->succeed($return);
                return;
            }
            list($promise, $noWait, $return) = $this->promisifyGeneratorYield($gen, $return);
            $this->immediately(function() use ($gen, $promisor, $return, $promise, $noWait) {
                if ($noWait) {
                    $this->sendToGenerator($gen, $promisor, $return);
                } else {
                    $promise->when(function($error, $result) use ($gen, $promisor, $return) {
                        $this->sendToGenerator($gen, $promisor, $return, $error, $result);
                    });
                }
            });
        } catch (\Exception $uncaught) {
            $promisor->fail($uncaught);
        }
    }

    private function sendToGenerator(\Generator $gen, Promisor $promisor, $return = null, \Exception $error = null, $result = null) {
        try {
            if ($error) {
                $gen->throw($error);
            } else {
                $gen->send($result);
            }
            $this->advanceGenerator($gen, $promisor, $return);
        } catch (\Exception $uncaught) {
            $promisor->fail($uncaught);
        }
    }

    private function promisifyGeneratorYield(\Generator $gen, $return) {
        $noWait = false;
        $promise = null;

        $key = $gen->key();
        $yielded = $gen->current();

        if (is_string($key)) {
            goto explicit_key;
        }

        // Fall through to implicit_key if no string key was yielded

        implicit_key: {
            if (!isset($yielded)) {
                $promise = new Success;
            } elseif (!is_object($yielded)) {
                $promise = new Failure(new \LogicException(
                    sprintf(
                        "Unresolvable implicit yield of type %s; key required",
                        is_object($yielded) ? get_class($yielded) : gettype($yielded)
                    )
                ));
            } elseif ($yielded instanceof Promise) {
                $promise = $yielded;
            } elseif ($yielded instanceof \Generator) {
                $promise = $this->coroutine($yielded);
            } else {
                $promise = new Failure(new \LogicException(
                    sprintf(
                        "Unresolvable implicit yield of type %s; key required",
                        is_object($yielded) ? get_class($yielded) : gettype($yielded)
                    )
                ));
            }

            goto return_struct;
        }

        explicit_key: {
            $key = strtolower($key);
            if ($key[0] === self::NOWAIT_PREFIX) {
                $noWait = true;
                $key = substr($key, 1);
            }

            switch ($key) {
                case self::ASYNC:
                    goto async;
                case self::COROUTINE:
                    goto coroutine;
                case self::CORETURN:
                    goto coreturn;
                case self::ALL:
                    // fallthrough
                case self::ANY:
                    // fallthrough
                case self::SOME:
                    goto combinator;
                case self::PAUSE:
                    goto pause;
                case self::BIND:
                    goto bind;
                case self::IMMEDIATELY:
                    goto immediately;
                case self::ONCE:
                    // fallthrough
                case self::REPEAT:
                    goto schedule;
                case self::ON_READABLE:
                    $ioWatchMethod = 'onReadable';
                    goto io_watcher;
                case self::ON_WRITABLE:
                    $ioWatchMethod = 'onWritable';
                    goto io_watcher;
                case self::ENABLE:
                    // fallthrough
                case self::DISABLE:
                    // fallthrough
                case self::CANCEL:
                    goto watcher_control;
                case self::NOWAIT:
                    $noWait = true;
                    goto implicit_key;
                default:
                    if ($noWait) {
                        $promise = new Failure(new \LogicException(
                            'Cannot use standalone @ "nowait" prefix'
                        ));
                        goto return_struct;
                    } else {
                        goto unknown_key;
                    }
            }
        }

        coreturn: {
            $return = $yielded;
            $promise = new Success;
            goto return_struct;
        }

        unknown_key: {
            $promise = new Failure(new \DomainException(
                sprintf("Unknown yield key: %s", $key)
            ));
            goto return_struct;
        }

        async: {
            if (is_object($yielded) && $yielded instanceof Promise) {
                $promise = $yielded;
            } else {
                $promise = new Failure(new \DomainException(
                    sprintf(
                        "%s yield command expects Promise; %s yielded",
                        $key,
                        gettype($yielded)
                    )
                ));
            }
            goto return_struct;
        }

        coroutine: {
            if (is_object($yielded) && $yielded instanceof \Generator) {
                $promise = $this->coroutine($yielded);
            } else {
                $promise = new Failure(new \DomainException(
                    sprintf(
                        "%s yield command expects Generator; %s yielded",
                        $key,
                        gettype($yielded)
                    )
                ));
            }
            goto return_struct;
        }

        combinator: {
            if (!is_array($yielded)) {
                $promise = new Failure(new \DomainException(
                    sprintf("%s yield command expects array; %s yielded", $key, gettype($yielded))
                ));
                goto return_struct;
            }

            $promises = [];
            foreach ($yielded as $index => $element) {
                if ($element instanceof Promise) {
                    $promise = $element;
                } elseif ($element instanceof \Generator) {
                    $promise = $this->coroutine($element);
                } else {
                    $promise = new Success($element);
                }

                $promises[$index] = $promise;
            }

            $combinatorFunction = __NAMESPACE__ . "\\{$key}";
            $promise = $combinatorFunction($promises);

            goto return_struct;
        }

        immediately: {
            if (is_callable($yielded)) {
                $watcherId = $this->immediately($yielded);
                $promise = new Success($watcherId);
            } else {
                $promise = new Failure(new \DomainException(
                    sprintf(
                        "%s yield command requires callable; %s provided",
                        $key,
                        gettype($yielded)
                    )
                ));
            }

            goto return_struct;
        }

        schedule: {
            if (is_array($yielded) && isset($yielded[0], $yielded[1]) && is_callable($yielded[0])) {
                list($func, $msDelay) = $yielded;
                $watcherId = $this->{$key}($func, $msDelay);
                $promise = new Success($watcherId);
            } else {
                $promise = new Failure(new \DomainException(
                    sprintf(
                        "%s yield command requires [callable \$func, int \$msDelay]; %s provided",
                        $key,
                        gettype($yielded)
                    )
                ));
            }

            goto return_struct;
        }

        io_watcher: {
            if (is_array($yielded) && isset($yielded[0], $yielded[1]) && is_callable($yielded[1])) {
                list($stream, $func, $enableNow) = $yielded;
                $watcherId = $this->{$ioWatchMethod}($stream, $func, $enableNow);
                $promise = new Success($watcherId);
            } else {
                $promise = new Failure(new \DomainException(
                    sprintf(
                        "%s yield command requires [resource \$stream, callable \$func, bool \$enableNow]; %s provided",
                        $key,
                        gettype($yielded)
                    )
                ));
            }

            goto return_struct;
        }

        pause: {
            $promise = new Future;
            $this->once(function() use ($promise) {
                $promise->succeed();
            }, (int) $yielded);

            goto return_struct;
        }

        bind: {
            if (is_callable($yielded)) {
                $promise = new Success(function() use ($yielded) {
                    $result = call_user_func_array($yielded, func_get_args());
                    return $result instanceof \Generator
                        ? $this->coroutine($result)
                        : $result;
                });
            } else {
                $promise = new Failure(new \DomainException(
                    sprintf("bind yield command requires callable; %s provided", gettype($yielded))
                ));
            }

            goto return_struct;
        }

        watcher_control: {
            $this->{$key}($yielded);
            $promise = new Success;

            goto return_struct;
        }

        return_struct: {
            return [$promise, $noWait, $return];
        }
    }
}
