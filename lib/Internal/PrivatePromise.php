<?php declare(strict_types = 1);

namespace Amp\Internal;

use Interop\Async\Promise;

/**
 * An promise that cannot be externally resolved. Used by Deferred in development mode.
 *
 * @internal
 */
final class PrivatePromise implements Promise {
    use Placeholder;

    /**
     * @param callable(callable $resolve, callable $reject): void $resolver
     */
    public function __construct(callable $resolver) {
        /**
         * Resolves the promise with the given promise or value.
         *
         * @param mixed $value
         */
        $resolve = function ($value = null) {
            $this->resolve($value);
        };
        
        /**
         * Fails the promise with the given exception.
         *
         * @param \Throwable $reason
         */
        $fail = function (\Throwable $reason) {
            $this->fail($reason);
        };

        $resolver($resolve, $fail);
    }
}
