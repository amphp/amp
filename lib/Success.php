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
     * {@inheritDoc}
     */
    public function when(callable $func, $data = null) {
        $func($error = null, $this->result, $data);
    }

    /**
     * {@inheritDoc}
     */
    public function watch(callable $func, $data = null) {
        return;
    }
}
