<?php

namespace Amp;

/**
 * Represents a failed computation resolution
 */
class Failure implements Promise {
    private $error;

    public function __construct(\Exception $error) {
        $this->error = $error;
    }

    /**
     * Pass the resolved failure Exception to the specified callback
     *
     * NOTE: because this object represents a failed Promise it will *always* invoke the specified
     * $func callback immediately.
     *
     * @return void
     */
    public function when(callable $func) {
        $func($this->error, $result = null);
    }

    /**
     * Does nothing -- a resolved promise has no progress updates
     *
     * @return void
     */
    public function watch(callable $func) {
        return;
    }

    /**
     * This method is deprecated. New code should use Amp\wait($promise) instead.
     */
    public function wait() {
        trigger_error(
            'Amp\\Promise::wait() is deprecated and scheduled for removal. ' .
            'Please update code to use Amp\\wait($promise) instead.',
            E_USER_DEPRECATED
        );

        throw $this->error;
    }
}
