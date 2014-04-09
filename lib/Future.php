<?php

namespace Alert;

/**
 * A "placeholder" value that will be resolved at some point in the future.
 */
interface Future {

    /**
     * Pass the Future to the specified callback upon completion regardless of success or failure
     *
     * @param callable $onComplete
     * @return Future Returns the current object instance
     */
    public function onComplete(callable $onComplete);

    /**
     * Has the Future completed (succeeded/failure is irrelevant)?
     *
     * @return bool
     */
    public function isComplete();

    /**
     * Has the Future value been successfully resolved?
     *
     * @throws \LogicException If the Future is still pending
     * @return bool
     */
    public function succeeded();

    /**
     * Retrieve the value that successfully fulfilled the Future
     *
     * @throws \LogicException If the Future is still pending
     * @throws \Exception If the Future failed the exception that caused the failure is thrown
     * @return mixed
     */
    public function getValue();

    /**
     * Retrieve the Exception responsible for Future resolution failure
     *
     * @throws \LogicException If the Future succeeded or is still pending
     * @return \Exception
     */
    public function getError();
}
