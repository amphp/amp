<?php

namespace Amp\Internal;

use Amp\Observable;
use Interop\Async\Awaitable;

/**
 * An observable that cannot externally emit values. Used by Postponed in development mode.
 *
 * @internal
 */
final class PrivateObservable implements Observable {
    use Producer;

    /**
     * @param callable(callable $emit, callable $complete, callable $fail): void $emitter
     */
    public function __construct(callable $emitter) {
        $this->init();

        /**
         * Emits a value from the observable.
         *
         * @param mixed $value
         *
         * @return \Interop\Async\Awaitable
         */
        $emit = function ($value = null): Awaitable {
            return $this->emit($value);
        };

        /**
         * Completes the observable with the given value.
         *
         * @param mixed $value
         */
        $resolve = function ($value = null) {
            $this->resolve($value);
        };

        /**
         * Fails the observable with the given exception.
         *
         * @param \Throwable $reason
         */
        $fail = function (\Throwable $reason) {
            $this->fail($reason);
        };

        try {
            $emitter($emit, $resolve, $fail);
        } catch (\Throwable $exception) {
            $this->fail($exception);
        }
    }
}
