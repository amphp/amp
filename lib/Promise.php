<?php

namespace Alert;

/**
 * A "placeholder" value that will be resolved at some point in the future
 */
class Promise implements Future, Promisor {
    use Resolvable;

    /**
     * Retrieve the Future value associated with this Promise
     *
     * @return Alert\Future
     */
    public function getFuture() {
        return $this;
    }

    /**
     * Fufill the Promisor's Future value with a successful result
     *
     * @param mixed $value
     * @throws LogicException If the Future is already resolved
     * @return void
     */
    public function succeed($value = NULL) {
        if ($this->isComplete) {
            throw new \LogicException(
                'Cannot succeed: Future already resolved'
            );
        }

        if ($value instanceof Future) {
            $value->onComplete(function(Future $f) {
                $this->resolve($f->getError(), $f->getValue());
            });
            return;
        }

        $this->isComplete = TRUE;
        $this->value = $value;
        if ($this->onComplete) {
            foreach ($this->onComplete as $onComplete) {
                call_user_func($onComplete, $this);
            }
        }
    }

    /**
     * Fail the Promisor's associated Future
     *
     * @param Exception $error
     * @throws LogicException If the Future is already resolved
     * @return void
     */
    public function fail(\Exception $error) {
        if ($this->isComplete) {
            throw new \LogicException(
                'Cannot fail: Future already resolved'
            );
        }

        $this->isComplete = TRUE;
        $this->error = $error;
        if ($this->onComplete) {
            foreach ($this->onComplete as $onComplete) {
                call_user_func($onComplete, $this);
            }
        }
    }

    /**
     * Resolve the Promisor's associated Future
     *
     * @param Exception $error
     * @param mixed $value
     * @throws LogicException If the Future is already resolved
     * @return void
     */
    public function resolve(\Exception $error = NULL, $value = NULL) {
        if ($this->isComplete) {
            throw new \LogicException(
                'Cannot succeed: Future already resolved'
            );
        }

        $this->isComplete = TRUE;
        if ($error) {
            $this->error = $error;
        } else {
            $this->value = $value;
        }

        if ($this->onComplete) {
            foreach ($this->onComplete as $onComplete) {
                call_user_func($onComplete, $this);
            }
        }
    }

    /**
     * Resolve the Promisor's Future but only if it has not previously resolved
     *
     * @param Exception $error
     * @param mixed $value
     * @return bool Returns TRUE if the Future was resolved by this operation or FALSE if the
     *              Promisor had previously resolved its Future
     */
    public function resolveSafely(\Exception $error = NULL, $value = NULL) {
        if ($this->isComplete) {
            $didResolve = FALSE;
        } else {
            $this->resolve($error, $value);
            $didResolve = TRUE;
        }

        return $didResolve;
    }
}
