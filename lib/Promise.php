<?php

namespace Amp;

/**
 * Representation of the future value of an asynchronous operation.
 *
 * @template-covariant TValue
 */
interface Promise
{
    /**
     * Registers a callback to be invoked when the promise is resolved.
     *
     * If this method is called multiple times, additional handlers will be registered instead of replacing any already
     * existing handlers.
     *
     * If the promise is already resolved, the callback MUST be executed immediately.
     *
     * Exceptions MUST NOT be thrown from this method. Any exceptions thrown from invoked callbacks MUST be
     * forwarded to the event-loop error handler.
     *
     * Note: You shouldn't implement this interface yourself. Instead, provide a method that returns a promise for the
     * operation you're implementing. Objects other than pure placeholders implementing it are a very bad idea.
     *
     * @param callable(\Throwable|null $reason, TValue|null $value) $onResolved `$reason` shall be `null` on
     *     success, `$value` shall be `null` on failure.
     *
     * @return void
     */
    public function onResolve(callable $onResolved);
}
