<?php

namespace Amp;

/**
 * A placeholder value for the deferred result of an asynchronous computation
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
     * @param callable $func An error-first callback to invoke upon promise resolution
     * @param mixed $data Optional data to pass as a third parameter to $func
     * @return void
     */
    public function when(callable $func, $data = null);

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
     * @param callable $func A callback to invoke when data updates are available
     * @param mixed $data Optional data to pass as an additional parameter to $func
     * @return void
     */
    public function watch(callable $func, $data = null);
}
