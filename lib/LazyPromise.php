<?php

namespace Amp;

use React\Promise\PromiseInterface as ReactPromise;

/**
 * Creates a promise that calls $promisor only when the result of the promise is requested (i.e. onResolve() is called
 * on the promise). $promisor can return a promise or any value. If $promisor throws an exception, the promise fails
 * with that exception.
 */
class LazyPromise implements Promise {
    /** @var callable|null */
    private $promisor;

    /** @var \Amp\Promise|null */
    private $promise;

    /**
     * @param callable $promisor Function which starts an async operation, returning a Promise or any value.
     */
    public function __construct(callable $promisor) {
        $this->promisor = $promisor;
    }

    /**
     * {@inheritdoc}
     */
    public function onResolve(callable $onResolved) {
        if ($this->promise === null) {
            $provider = $this->promisor;
            $this->promisor = null;

            try {
                $this->promise = $provider();

                if ($this->promise instanceof \Generator) {
                    $this->promise = new Coroutine($this->promise);
                } elseif ($this->promise instanceof ReactPromise) {
                    $this->promise = Promise\adapt($this->promise);
                } elseif (!$this->promise instanceof Promise) {
                    $this->promise = new Success($this->promise);
                }
            } catch (\Throwable $exception) {
                $this->promise = new Failure($exception);
            }
        }

        $this->promise->onResolve($onResolved);
    }
}