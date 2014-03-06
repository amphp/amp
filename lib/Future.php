<?php

namespace Alert;

/**
 * A "placeholder" value that will be resolved at some point in the future.
 */
class Future {
    private $onSuccess = [];
    private $onFailure = [];
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
     * Pass the Future to the specified callback upon successful completion
     *
     * @param callable $onSuccess
     * @return Future Returns the current object instance
     */
    public function onSuccess(callable $onSuccess) {
        if (!$this->isComplete) {
            $this->onSuccess[] = $onSuccess;
        } elseif (!$this->error) {
            call_user_func($onSuccess, $this);
        }

        return $this;
    }

    /**
     * Pass the Future to the specified callback upon failed completion
     *
     * @param callable $onFailure
     * @return Future Returns the current object instance
     */
    public function onFailure(callable $onFailure) {
        if (!$this->isComplete) {
            $this->onFailure[] = $onFailure;
        } elseif ($this->error) {
            call_user_func($onFailure, $this);
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
     * Is the Future still awaiting completion?
     *
     * @return bool
     */
    public function isPending() {
        return !$this->isComplete;
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
     * Has the Future failed?
     *
     * @throws \LogicException If the Future is still pending
     * @return bool
     */
    public function failed() {
        if ($this->isComplete) {
            return (bool) $this->error;
        } else {
            throw new \LogicException(
                'Cannot retrieve failure status: Future still pending'
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
     * @return mixed
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
        return $error ? $this->fail($error) : $this->succeed($value);
    }

    private function fail(\Exception $error) {
        if ($this->isComplete) {
            throw new \LogicException(
                'Cannot fail: Future already resolved'
            );
        }

        $this->isComplete = TRUE;
        $this->error = $error;

        if ($this->onFailure) {
            foreach ($this->onFailure as $onFailure) {
                call_user_func($onFailure, $this);
            }
        }
        if ($this->onComplete) {
            foreach ($this->onComplete as $onComplete) {
                call_user_func($onComplete, $this);
            }
        }
    }

    private function succeed($value) {
        if ($this->isComplete) {
            throw new \LogicException(
                'Cannot succeed: Future already resolved'
            );
        }

        $this->isComplete = TRUE;
        $this->value = $value;

        if ($this->onSuccess) {
            foreach ($this->onSuccess as $onSuccess) {
                call_user_func($onSuccess, $this);
            }
        }
        if ($this->onComplete) {
            foreach ($this->onComplete as $onComplete) {
                call_user_func($onComplete, $this);
            }
        }
    }
}
