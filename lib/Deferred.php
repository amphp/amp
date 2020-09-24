<?php

namespace Amp;

/**
 * Deferred is a container for a promise that is resolved using the resolve() and fail() methods of this object.
 * The contained promise may be accessed using the promise() method. This object should not be part of a public
 * API, but used internally to create and resolve a promise.
 *
 * @template TValue
 */
final class Deferred
{
    private Promise $resolver;

    private Internal\PrivatePromise $promise;

    public function __construct()
    {
        $this->resolver = new class implements Promise {
            use Internal\Placeholder {
                resolve as public;
                fail as public;
                isResolved as public;
            }
        };

        $this->promise = new Internal\PrivatePromise($this->resolver);
    }

    /**
     * @return Promise<TValue>
     */
    public function promise(): Promise
    {
        return $this->promise;
    }

    /**
     * @return bool True if the contained promise has been resolved.
     */
    public function isResolved(): bool
    {
        /** @psalm-suppress UndefinedInterfaceMethod */
        return $this->resolver->isResolved();
    }

    /**
     * Fulfill the promise with the given value.
     *
     * @param mixed $value
     *
     * @psalm-param TValue|Promise<TValue> $value
     *
     * @return void
     */
    public function resolve(mixed $value = null): void
    {
        /** @psalm-suppress UndefinedInterfaceMethod */
        $this->resolver->resolve($value);
    }

    /**
     * Fails the promise the the given reason.
     *
     * @param \Throwable $reason
     *
     * @return void
     */
    public function fail(\Throwable $reason): void
    {
        /** @psalm-suppress UndefinedInterfaceMethod */
        $this->resolver->fail($reason);
    }
}
