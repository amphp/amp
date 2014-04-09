<?php

namespace Alert;

/**
 * A placeholder for a resolved failure
 */
class Failure implements Future {
    private $error;

    public function __construct(\Exception $error) {
        $this->error = $error;
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
     * Has the Future completed (succeeded/failure is irrelevant)?
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
        return FALSE;
    }

    /**
     * Retrieve the value that successfully fulfilled the Future
     *
     * @throws \Exception
     */
    public function getValue() {
        throw $this->error;
    }

    /**
     * Retrieve the Exception responsible for Future resolution failure
     *
     * @return \Exception
     */
    public function getError() {
        return $this->error;
    }
}
