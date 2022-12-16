<?php declare(strict_types=1);

namespace Amp;

/**
 * @template T
 */
final class DeferredFuture
{
    use ForbidCloning;
    use ForbidSerialization;

    /** @var Internal\FutureState<T> */
    private readonly Internal\FutureState $state;

    /** @var Future<T> */
    private readonly Future $future;

    public function __construct()
    {
        $this->state = new Internal\FutureState();
        $this->future = new Future($this->state);
    }

    /**
     * Completes the operation with a result value.
     *
     * @param T $value Result of the operation.
     */
    public function complete(mixed $value = null): void
    {
        $this->state->complete($value);
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
