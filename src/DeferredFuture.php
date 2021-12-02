<?php

namespace Amp;

/**
 * @template T
 */
final class DeferredFuture
{
    /** @var Internal\FutureState<T> */
    private Internal\FutureState $state;

    /** @var Future<T> */
    private Future $future;

    public function __construct()
    {
        $this->state = new Internal\FutureState();
        $this->future = new Future($this->state);
    }

    /**
     * Completes the operation with a result value.
     *
     * @param T $result Result of the operation.
     */
    public function complete(mixed $result = null): void
    {
        $this->state->complete($result);
    }

    /**
     * Marks the operation as failed.
     *
     * @param \Throwable $throwable Throwable to indicate the error.
     */
    public function error(\Throwable $throwable): void
    {
        $this->state->error($throwable);
    }

    /**
     * @return bool True if the operation has completed.
     */
    public function isComplete(): bool
    {
        return $this->state->isComplete();
    }

    /**
     * @return Future<T> The future associated with this Deferred.
     */
    public function getFuture(): Future
    {
        return $this->future;
    }
}
