<?php declare(strict_types = 1);

namespace Amp\Internal;

use Interop\Async\Awaitable;

/**
 * An awaitable that cannot be externally resolved. Used by Deferred in development mode.
 *
 * @internal
 */
final class PrivateAwaitable implements Awaitable {
    use Placeholder;

    /**
     * @param callable(callable $resolve, callable $reject): void $resolver
     */
    public function __construct(callable $resolver) {
        /**
         * Resolves the awaitable with the given awaitable or value.
         *
         * @param mixed $value
         */
        $resolve = function ($value = null) {
            $this->resolve($value);
        };
        
        /**
         * Fails the awaitable with the given exception.
         *
         * @param \Throwable $reason
         */
        $fail = function (\Throwable $reason) {
            $this->fail($reason);
        };

        try {
            $resolver($resolve, $fail);
        } catch (\Throwable $exception) {
            $this->fail($exception);
        }
    }
}
