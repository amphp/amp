<?php

namespace Alert;

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
     * Wait for Future value resolution
     *
     * NOTE: because this object represents a successfully resolved Promise it will *always* return
     * the resolved result immediately.
     *
     * @return mixed
     */
    public function wait() {
        return $this->result;
    }
}
