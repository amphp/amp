<?php

namespace Amp;

/**
 * Creates a promise that calls $promisor only when the result of the promise is requested (i.e. onResolve() is called
 * on the promise). $promisor can return a promise or any value. If $promisor throws an exception, the promise fails
 * with that exception.
 */
final class LazyPromise implements Promise
{
    /** @var callable|null */
    private $promisor;

    /** @var Promise|null */
    private ?Promise $promise;

    /**
     * @param callable $promisor Function which starts an async operation, returning a Promise (or any value).
     *     Generators will be run as a coroutine.
     */
    public function __construct(callable $promisor)
    {
        $this->promisor = $promisor;
    }

    /**
     * @inheritDoc
     */
    public function onResolve(callable $onResolved): void
    {
        if (!isset($this->promise)) {
            \assert($this->promisor !== null);

            $provider = $this->promisor;
            $this->promisor = null;
            $this->promise = async($provider);
        }

        \assert($this->promise !== null);

        $this->promise->onResolve($onResolved);
    }
}
