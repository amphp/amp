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
     * {@inheritDoc}
     */
    public function when(callable $func, $data = null) {
        $func($this->error, $result = null, $data);
    }

    /**
     * {@inheritDoc}
     */
    public function watch(callable $func, $data = null) {
        return;
    }
}
