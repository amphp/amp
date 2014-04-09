<?php

namespace Alert;

/**
 * A "placeholder" value that will be resolved at some point in the future by
 * an associated Promise holder.
 */
class Unresolved implements Future {
    private $onComplete = [];
    private $isComplete = FALSE;
    private $value;
    private $error;

    /**
     * Pass the Future to the specified callback upon completion regardless of success or failure
     *
     * @param callable $onComplete
     * @return Future Returns the current object instance
     */
    public function onComplete(callable $onComplete) {
        if ($this->isComplete) {
            call_user_func($onComplete, $this);
        } else {
            $this->onComplete[] = $onComplete;
        }

        return $this;
    }

    /**
     * Has the Future completed (succeeded/failure is irrelevant)?
     *
     * @return bool
     */
    public function isComplete() {
        return $this->isComplete;
    }

    /**
     * Has the Future value been successfully resolved?
     *
     * @throws \LogicException If the Future is still pending
     * @return bool
     */
    public function succeeded() {
        if ($this->isComplete) {
            return empty($this->error);
        } else {
            throw new \LogicException(
                'Cannot retrieve success status: Future still pending'
            );
        }
    }

    /**
     * Retrieve the value that successfully fulfilled the Future
     *
     * @throws \LogicException If the Future is still pending
     * @throws \Exception If the Future failed the exception that caused the failure is thrown
     * @return mixed
     */
    public function getValue() {
        if (!$this->isComplete) {
            throw new \LogicException(
                'Cannot retrieve value: Future still pending'
            );
        } elseif ($this->error) {
            throw $this->error;
        } else {
            return $this->value;
        }
    }

    /**
     * Retrieve the Exception responsible for Future resolution failure
     *
     * @throws \LogicException If the Future succeeded or is still pending
     * @return \Exception
     */
    public function getError() {
        if ($this->isComplete) {
            return $this->error;
        } else {
            throw new \LogicException(
                'Cannot retrieve error: Future still pending'
            );
        }
    }

    private function resolve(\Exception $error = NULL, $value = NULL) {
        if ($this->isComplete) {
            throw new \LogicException(
                'Cannot succeed: Future already resolved'
            );
        }

        $this->isComplete = TRUE;
        $this->error = $error;
        $this->value = $value;

        if ($this->onComplete) {
            foreach ($this->onComplete as $onComplete) {
                call_user_func($onComplete, $this);
            }
        }
    }

    /**
     * Create a new Future that will resolve when all Futures in the array resolve
     *
     * @param array $futures
     * @return Future
     */
    public static function all(array $futures) {
        return (new PromiseGroup($futures))->getFuture();
    }
}
