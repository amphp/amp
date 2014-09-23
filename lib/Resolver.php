<?php

namespace Amp;

class Resolver {
    private $reactor;

    /**
     * @param \Amp\Reactor $reactor
     */
    public function __construct(Reactor $reactor = null) {
        $this->reactor = $reactor ?: \Amp\reactor();
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
        $future = new Future($this->reactor);
        $this->advance($gen, $future);

        return $future;
    }

    private function advance(\Generator $gen, Future $future, $previousResult = null) {
        try {
            $current = $gen->current();
        } catch (\Exception $e) {
            return $future->fail($e);
        }

        if ($current instanceof Promise) {
            $current->when(function($error, $result) use ($gen, $future) {
                $this->send($gen, $future, $error, $result);
            });
        } elseif ($current instanceof \Generator) {
            $this->resolve($current)->when(function($error, $result) use ($gen, $future) {
                $this->send($gen, $future, $error, $result);
            });
        } elseif ($gen->valid()) {
            $this->send($gen, $future, $error = null, $current);
        } else {
            $future->succeed($previousResult);
        }
    }

    private function send(\Generator $gen, Future $future, \Exception $error = null, $result = null) {
        try {
            if ($error) {
                $gen->throw($error);
            } else {
                $gen->send($result);
            }
            $this->advance($gen, $future, $result);
        } catch (\Exception $error) {
            $future->fail($error);
        }
    }
}
