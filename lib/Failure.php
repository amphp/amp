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
     * @param \Exception|\Throwable $error
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
     * the specified $cb callback immediately.
     */
    public function when(callable $cb, $cbData = null) {
        \call_user_func($cb, $this->error, $result = null, $cbData);

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
