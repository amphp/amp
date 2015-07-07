<?php

namespace Amp;

/**
 * Represents a failed computation resolution
 */
class Failure implements Promise {
    private $error;

    /**
     * The error parameter used to fail a promisor must always be an exception
     * instance. However, we cannot typehint this parameter in environments
     * where PHP5.x compatibility is required because PHP7 Throwable
     * instances will break the typehint.
     */
    public function __construct($error) {
        if (!($error instanceof \Exception || $error instanceof \Throwable)) {
            throw new \InvalidArgumentException(
                "Only exceptions may be used to fail a promise"
            );
        }
        $this->error = $error;
    }

    /**
     * {@inheritDoc}
     */
    public function when(callable $func, $data = null) {
        $func($this->error, $result = null, $data);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function watch(callable $func, $data = null) {
        return $this;
    }
}
