<?php

namespace Alert;

/**
 * A placeholder for a successfully resolved value
 */
class Success implements Future {
    private $value;

    public function __construct($value = NULL) {
        $this->value = $value;
    }

    /**
     * Pass the Future to the specified callback upon completion regardless of success or failure
     *
     * @param callable $onComplete
     */
    public function onComplete(callable $onComplete) {
        call_user_func($onComplete, $this);
    }

    /**
     * Has the Future completed (success is irrelevant)?
     *
     * @return bool
     */
    public function isComplete() {
        return TRUE;
    }

    /**
     * Has the Future value been successfully resolved?
     *
     * @return bool
     */
    public function succeeded() {
        return TRUE;
    }

    /**
     * Retrieve the value that successfully fulfilled the Future
     *
     * @return mixed
     */
    public function getValue() {
        return $this->value;
    }

    /**
     * Retrieve the Exception responsible for Future resolution failure
     *
     * @return NULL
     */
    public function getError() {
        return NULL;
    }
}
