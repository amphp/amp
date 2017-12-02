<?php

namespace Amp;

// Issue a notice if AMP_OPTIMIZATIONS is not defined.
if (\getenv("AMP_OPTIMIZATIONS") === false && !\defined("AMP_OPTIMIZATIONS")) {
    \trigger_error("Amp is running in development mode. Define environment variable AMP_OPTIMIZATIONS or "
        . "const AMP_OPTIMIZATIONS to hide this notice. Use a truthy value to run in production mode", E_USER_NOTICE);
}

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
