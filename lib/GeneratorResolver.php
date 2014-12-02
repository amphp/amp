<?php

namespace Amp;

trait GeneratorResolver {
    private function resolveGenerator(\Generator $gen) {
        $promisor = new Future;
        $this->advanceGenerator($gen, $promisor);

        return $promisor;
    }

    private function advanceGenerator(\Generator $gen, Promisor $promisor, $previous = null) {
        try {
            if ($gen->valid()) {
                $key = $gen->key();
                $current = $gen->current();
                $promiseStruct = $this->promisifyGeneratorYield($key, $current);
                $this->immediately(function() use ($gen, $promisor, $promiseStruct) {
                    list($promise, $noWait) = $promiseStruct;
                    if ($noWait) {
                        $this->sendToGenerator($gen, $promisor);
                    } else {
                        $promise->when(function($error, $result) use ($gen, $promisor) {
                            $this->sendToGenerator($gen, $promisor, $error, $result);
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

    private function promisifyGeneratorYield($key, $current) {
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
                case YieldCommands::PAUSE:
                    goto pause;
                case YieldCommands::BIND:
                    goto bind;
                case YieldCommands::IMMEDIATELY:
                    goto immediately;
                case YieldCommands::ONCE:
                    // fallthrough
                case YieldCommands::REPEAT:
                    goto schedule;
                case YieldCommands::ON_READABLE:
                    $ioWatchMethod = 'onReadable';
                    goto io_watcher;
                case YieldCommands::ON_WRITABLE:
                    $ioWatchMethod = 'onWritable';
                    goto io_watcher;
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
                $promise = $this->resolveGenerator($current);
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
                    $promise = $this->resolveGenerator($element);
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
            if (is_callable($current)) {
                $watcherId = $this->immediately($current);
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
                $watcherId = $this->{$key}($func, $msDelay);
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

        io_watcher: {
            if (is_array($current) && isset($current[0], $current[1]) && is_callable($current[1])) {
                list($stream, $func, $enableNow) = $current;
                $watcherId = $this->{$ioWatchMethod}($stream, $func, $enableNow);
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

        pause: {
            $promisor = new Future;
            $this->once(function() use ($promisor) {
                $promisor->succeed();
            }, (int) $current);

            $promise = $promisor;

            goto return_struct;
        }

        bind: {
            if (is_callable($current)) {
                $promise = new Success(function() use ($current) {
                    $result = call_user_func_array($current, func_get_args());
                    return $result instanceof \Generator
                        ? $this->resolveGenerator($result)
                        : $result;
                });
            } else {
                $promise = new Failure(new \DomainException(
                    sprintf('"bind" yield command requires callable; %s provided', gettype($current))
                ));
            }

            goto return_struct;
        }

        watcher_control: {
            $this->{$key}($current);
            $promise = new Success;

            goto return_struct;
        }

        return_struct: {
            return [$promise, $noWait];
        }
    }

    private function sendToGenerator(\Generator $gen, Promisor $promisor, \Exception $error = null, $result = null) {
        try {
            if ($error) {
                $gen->throw($error);
            } else {
                $gen->send($result);
            }
            $this->advanceGenerator($gen, $promisor, $result);
        } catch (\Exception $error) {
            $promisor->fail($error);
        }
    }
}
