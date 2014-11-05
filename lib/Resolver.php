<?php

namespace Amp;

class Resolver {
    private $reactor;
    private $combinator;

    /**
     * @param \Amp\Reactor $reactor
     * @param \Amp\Combinator $combinator
     */
    public function __construct(Reactor $reactor = null, Combinator $combinator = null) {
        $this->reactor = $reactor ?: \Amp\reactor();
        $this->combinator = $combinator ?: new Combinator($this->reactor);
    }

    /**
     * A co-routine to resolve Generators
     *
     * Returns a promise that will resolve when the generator completes. The final value yielded by
     * the generator is used to resolve the returned promise on success.
     *
     * Generators are expected to yield Promise instances and/or other Generator instances.
     *
     * Example:
     *
     * $generator = function() {
     *     $a = (yield 2);
     *     $b = (yield new Success(21));
     *     yield $a * $b;
     * };
     *
     * resolve($generator())->when(function($error, $result) {
     *     var_dump($result); // int(42)
     * });
     *
     * @param \Generator
     * @return \Amp\Promise
     */
    public function resolve(\Generator $gen) {
        $promisor = new Future($this->reactor);
        $this->advance($gen, $promisor);

        return $promisor;
    }

    private function advance(\Generator $gen, Promisor $promisor, $previousResult = null) {
        try {
            if ($gen->valid()) {
                $key = $gen->key();
                $current = $gen->current();
                $promiseStruct = $this->promisifyYield($key, $current);
                $this->reactor->immediately(function() use ($gen, $promisor, $promiseStruct) {
                    list($promise, $noWait) = $promiseStruct;
                    if ($noWait) {
                        $this->send($gen, $promisor, $error = null, $result = null);
                    } else {
                        $promise->when(function($error, $result) use ($gen, $promisor) {
                            $this->send($gen, $promisor, $error, $result);
                        });
                    }
                });
            } else {
                $promisor->succeed($previousResult);
            }
        } catch (\Exception $error) {
            $promisor->fail($error);
        }
    }

    private function promisifyYield($key, $current) {
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
                $promise = $this->resolve($current);
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
                    $promise = $this->resolve($element);
                } else {
                    $promise = new Success($element);
                }

                $promises[$index] = $promise;
            }

            $promise = $this->combinator->{$key}($promises);

            goto return_struct;
        }

        immediately: {
            if (is_callable($current)) {
                $watcherId = $this->reactor->immediately($current);
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
                $watcherId = $this->reactor->{$key}($func, $msDelay);
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
                $watcherId = $this->reactor->{$ioWatchMethod}($stream, $func, $enableNow);
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
            $promisor = new Future($this->reactor);
            $this->reactor->once(function() use ($promisor) {
                $promisor->succeed();
            }, (int) $current);

            $promise = $promisor;

            goto return_struct;
        }

        watcher_control: {
            $this->reactor->{$key}($current);
            $promise = new Success;

            goto return_struct;
        }

        return_struct: {
            return [$promise, $noWait];
        }
    }

    private function send(\Generator $gen, Promisor $promisor, \Exception $error = null, $result = null) {
        try {
            if ($error) {
                $gen->throw($error);
            } else {
                $gen->send($result);
            }
            $this->advance($gen, $promisor, $result);
        } catch (\Exception $error) {
            $promisor->fail($error);
        }
    }
}
