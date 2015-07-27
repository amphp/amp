<?php

namespace Amp;

/**
 * A placeholder value for the deferred result of an asynchronous computation
 */
interface Promise {
    /**
     * Notify the $cb callback when the promise resolves (whether successful or not)
     *
     * Implementations MUST invoke the $cb callback in error-first style, e.g.:
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
     * @param callable $cb An error-first callback to invoke upon promise resolution
     * @param mixed $cbData Optional data to pass as a third parameter to $cb
     * @return self
     */
    public function when(callable $cb, $cbData = null);

    /**
     * Notify the $cb callback when resolution progress events are emitted
     *
     * Implementations MUST invoke $cb callback with a single update parameter, e.g.:
     *
     *     <?php
     *     $promise->watch(function($update) { ... });
     *
     * Implementations MUST return the current object instance.
     *
     * @param callable $cb A callback to invoke when data updates are available
     * @param mixed $cbData Optional data to pass as an additional parameter to $cb
     * @return self
     */
    public function watch(callable $cb, $cbData = null);
}
