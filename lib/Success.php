<?php

namespace Amp;

/**
 * Represents a successful computation resolution
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
     */
    public function when(callable $func, $data = null) {
        \call_user_func($func, $error = null, $this->result, $data);

        return $this;
    }

    /**
     * Does nothing -- a resolved promise has no progress updates
     */
    public function watch(callable $func, $data = null) {
        return $this;
    }
}
