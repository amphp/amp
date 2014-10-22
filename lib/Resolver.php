<?php

namespace Amp;

class Resolver {
    const ALL = 'all';
    const ANY = 'any';
    const SOME = 'some';
    const WAIT = 'wait';
    const ONCE = 'once';
    const REPEAT = 'repeat';
    const IMMEDIATELY = 'immediately';
    const WATCH_STREAM = 'watch-stream';
    const ENABLE = 'enable';
    const DISABLE = 'disable';
    const CANCEL = 'cancel';

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
                $yieldPromise = $this->promisify($key, $current);
                $this->reactor->immediately(function() use ($gen, $promisor, $yieldPromise) {
                    $yieldPromise->when(function($error, $result) use ($gen, $promisor) {
                        $this->send($gen, $promisor, $error, $result);
                    });
                });
            } else {
                $promisor->succeed($previousResult);
            }
        } catch (\Exception $error) {
            $promisor->fail($error);
        }
    }

    private function promisify($key, $current) {
        if ($current instanceof Promise) {
            return $current;
        } elseif ($key === (string) $key) {
            goto explicit_key;
        } else {
            goto implicit_key;
        }

        explicit_key: {
            switch ($key) {
                case self::ALL:
                    // fallthrough
                case self::ANY:
                    // fallthrough
                case self::SOME:
                    if (is_array($current)) {
                        goto combinator;
                    } else {
                        return new Failure(new \DomainException(
                            sprintf('"%s" key expects array; %s yielded', $key, gettype($current))
                        ));
                    }
                case self::WAIT:
                    goto wait;
                case self::IMMEDIATELY:
                    goto immediately;
                case self::ONCE:
                    // fallthrough
                case self::REPEAT:
                    goto schedule;
                case self::WATCH_STREAM:
                    goto watch_stream;
                case self::ENABLE:
                    // fallthrough
                case self::DISABLE:
                    // fallthrough
                case self::CANCEL:
                    goto watcher_control;
                default:
                    return new Failure(new \DomainException(
                        sprintf('Unknown yield key: %s', $key)
                    ));
            }
        }

        implicit_key: {
            if ($current instanceof \Generator) {
                return $this->resolve($current);
            } elseif (is_array($current)) {
                $key = self::ALL;
                goto combinator;
            } else {
                return new Success($current);
            }
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

            return $this->combinator->{$key}($promises);
        }

        immediately: {
            if (!is_callable($current)) {
                return new Failure(new \DomainException(
                    sprintf(
                        '"%s" yield requires callable; %s provided',
                        $key,
                        gettype($current)
                    )
                ));
            }

            $watcherId = $this->reactor->immediately($current);

            return new Success($watcherId);
        }

        schedule: {
            if (!($current && isset($current[0], $current[1]) && is_array($current))) {
                return new Failure(new \DomainException(
                    sprintf(
                        '"%s" yield requires [callable $func, int $msDelay]; %s provided',
                        $key,
                        gettype($current)
                    )
                ));
            }

            list($func, $msDelay) = $current;
            $watcherId = $this->reactor->{$key}($func, $msDelay);

            return new Success($watcherId);
        }

        watch_stream: {
            if (!($current &&
                isset($current[0], $current[1], $current[2]) &&
                is_array($current) &&
                is_callable($current[1])
            )) {
                return new Failure(new \DomainException(

                ));
            }

            list($stream, $callback, $flags) = $current;

            try {
                $watcherId = $this->reactor->watchStream($stream, $callback, $flags);
                return new Success($watcherId);
            } catch (\Exception $error) {
                return new Failure($error);
            }
        }

        wait: {
            $promisor = new Future($this->reactor);
            $this->reactor->once(function() use ($promisor) {
                $promisor->succeed();
            }, (int) $current);

            return $promisor;
        }

        watcher_control: {
            $this->reactor->{$key}($current);
            return new Success;
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
