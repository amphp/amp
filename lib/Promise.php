<?php

namespace Amp;

/**
 * Representation of the future value of an asynchronous operation.
 */
interface Promise {
    /**
     * Registers a callback to be invoked when the promise is resolved.
     *
     * If the promise is already resolved, the callback MUST be executed immediately.
     *
     * Exceptions MUST NOT be thrown from this method. Any exceptions thrown from invoked callbacks MUST be
     * forwarded to the event-loop error handler.
     *
     * @param callable(\Throwable|null $reason, $value) $onResolved `$reason` shall be `null` on
     *     success, `$value` shall be `null` on failure.
     *
     * @return void
     */
    public function onResolve(callable $onResolved);
}
