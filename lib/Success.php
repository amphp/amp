<?php

namespace Amp;

/**
 * A successfully resolved promise
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
     * {@inheritdoc}
     *
     * NOTE: because this object represents a resolved Promise it will *always* invoke
     * the specified $cb callback immediately.
     */
    public function when(callable $cb, $cbData = null) {
        \call_user_func($cb, $error = null, $this->result, $cbData);

        return $this;
    }

    /**
     * {@inheritdoc}
     * 
     * Does nothing; a resolved promise has no progress updates
     */
    public function watch(callable $cb, $cbData = null) {
        return $this;
    }
}
