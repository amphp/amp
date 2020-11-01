<?php

namespace Amp;

/**
 * Creates a failed promise using the given exception.
 *
 * @template-covariant TValue
 * @template-implements Promise<TValue>
 */
final class Failure implements Promise
{
    private \Throwable $exception;

    /**
     * @param \Throwable $exception Rejection reason.
     */
    public function __construct(\Throwable $exception)
    {
        $this->exception = $exception;
    }

    /**
     * {@inheritdoc}
     */
    public function onResolve(callable $onResolved): void
    {
        Loop::defer(fn() => $onResolved($this->exception, null));
    }
}
