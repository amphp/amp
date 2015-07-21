<?php

namespace Amp;

/**
 * A rejected (failed) promise
 */
class Failure implements Promise {
    private $error;

    /**
     * The error parameter used to fail a promisor must always be an exception
     * instance. However, we cannot typehint this parameter in environments
     * where PHP5.x compatibility is required because PHP7 Throwable
     * instances will break the typehint.
     * 
     * @param Exception $error
     * @TODO Add Throwable typehint and remove conditional once PHP7 is required
     */
    public function __construct($error) {
        if ($error instanceof \Throwable || $error instanceof \Exception) {
            $this->error = $error;
        } else {
            throw new \InvalidArgumentException(
                "Throwable Exception instance required"
            );
        }
    }

    /**
     * {@inheritdoc}
     *
     * NOTE: because this object represents a resolved Promise it will *always* invoke
     * the specified $func callback immediately.
     */
    public function when(callable $func, $data = null) {
        \call_user_func($func, $this->error, $result = null, $data);

        return $this;
    }

    /**
     * {@inheritdoc}
     * 
     * Does nothing; a resolved promise has no progress updates
     */
    public function watch(callable $func, $data = null) {
        return $this;
    }
}
