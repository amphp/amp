<?php

namespace Amp\Internal;

use Amp\Stream;
use AsyncInterop\Promise;

/**
 * An stream that cannot externally emit values. Used by Emitter in development mode.
 *
 * @internal
 */
final class PrivateStream implements Stream {
    use Producer;

    /**
     * @param callable(callable $emit, callable $complete, callable $fail): void $producer
     */
    public function __construct(callable $producer) {
        /**
         * Emits a value from the stream.
         *
         * @param mixed $value
         *
         * @return \AsyncInterop\Promise
         */
        $emit = function ($value = null): Promise {
            return $this->emit($value);
        };

        /**
         * Completes the stream with the given value.
         *
         * @param mixed $value
         */
        $resolve = function ($value = null) {
            $this->resolve($value);
        };

        /**
         * Fails the stream with the given exception.
         *
         * @param \Throwable $reason
         */
        $fail = function (\Throwable $reason) {
            $this->fail($reason);
        };

        $producer($emit, $resolve, $fail);
    }
}
