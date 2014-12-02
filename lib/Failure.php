<?php

namespace Amp;

/**
 * Represents the failed resolution of a Promisor's future computation
 */
class Failure implements Promise {
    private $error;

    /**
     * @param \Exception $error
     */
    public function __construct(\Exception $error) {
        $this->error = $error;
    }

    /**
     * Pass the resolved failure Exception to the specified callback
     *
     * NOTE: because this object represents a failed Promise it will *always* invoke the specified
     * $func callback immediately.
     *
     * @param callable $func
     * @return void
     */
    public function when(callable $func) {
        $func($this->error, $result = null);
    }

    /**
     * Does nothing -- a resolved promise has no progress updates
     *
     * @param callable $func
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
