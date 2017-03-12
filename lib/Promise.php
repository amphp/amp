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
     * @param callable(\Throwable|\Exception|null $reason, $value) $onResolved `$reason` shall be `null` on
     *     success, `$value` shall be `null` on failure.
     *
     * @return mixed Return type and value are unspecified.
     */
    public function when(callable $onResolved);
}