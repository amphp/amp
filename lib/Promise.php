<?php

namespace Amp\Awaitable;

use Interop\Async\Awaitable;

/**
 * A Promise is an awaitable that provides the functions to resolve or fail the promise to the resolver function
 * given to the constructor. A Promise cannot be externally resolved. Only the functions provided to the constructor
 * may resolve the Promise.
 */
final class Promise implements Awaitable {
    use Internal\Placeholder;

    /**
     * @param callable(callable $resolve, callable $reject): void $resolver
     */
    public function __construct(callable $resolver) {
        /**
         * Resolves the promise with the given promise or value. If another promise, this promise takes
         * on the state of that promise. If a value, the promise will be fulfilled with that value.
         *
         * @param mixed $value A promise can be resolved with anything other than itself.
         */
        $resolve = function ($value = null) {
            $this->resolve($value);
        };
        
        /**
         * Fails the promise with the given exception.
         *
         * @param \Exception $reason
         */
        $fail = function ($reason) {
            $this->fail($reason);
        };

        try {
            $resolver($resolve, $fail);
        } catch (\Throwable $exception) {
            $this->fail($exception);
        } catch (\Exception $exception) {
            $this->fail($exception);
        }
    }
}
