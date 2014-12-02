<?php

namespace Amp;

/**
 * A placeholder value for the future result of an asynchronous computation
 */
interface Promise {
    /**
     * Notify the $func callback when the promise resolves (whether successful or not)
     *
     * Implementations MUST invoke the $func callback in error-first style, e.g.:
     *
     *     <?php
     *     $promise->when(function(\Exception $error = null, $result = null) {
     *         if ($error) {
     *             // failed
     *         } else {
     *             // succeeded
     *         }
     *     });
     *
     * Implementations MUST return the current object instance.
     *
     * @param callable $func
     * @return self
     */
    public function when(callable $func);

    /**
     * Notify the $func callback when resolution progress events are emitted
     *
     * Implementations MUST invoke $func callback with a single update parameter, e.g.:
     *
     *     <?php
     *     $promise->watch(function($update) { ... });
     *
     * Implementations MUST return the current object instance.
     *
     * @param callable $func
     * @return self
     */
    public function watch(callable $func);
}
