<?php

namespace Amp;

/**
 * Represents the successful resolution of a Promisor's future computation
 */
class Success implements Promise {
    private $result;

    /**
     * @param mixed $result
     */
    public function __construct($result = null) {
        $this->result = $result;
    }

    /**
     * Pass the resolved result to the specified $func callback
     *
     * NOTE: because this object represents a successfully resolved Promise it will *always* invoke
     * the specified $func callback immediately.
     *
     * @param callable $func
     * @return void
     */
    public function when(callable $func) {
        $func($error = null, $this->result);
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

        return $this->result;
    }
}
