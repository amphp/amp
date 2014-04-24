<?php

namespace Alert;

/**
 * A SafePromise creates a Future constract that can *only* be fulfilled by calling methods
 * on the actual SafePromise instance. This provides an additional layer of API protection
 * over the standard Promise implementation whose Future can be resolved by any code with a
 * reference to the Future.
 */
class SafePromise implements Promisor {
    private $value;
    private $error;
    private $future;
    private $futureResolver;

    public function __construct() {
        $futureResolver = function(\Exception $error = NULL, $value = NULL) {
            $this->resolve($error, $value);
        };
        $future = new Unresolved;
        $this->futureResolver = $futureResolver->bindTo($future, $future);
        $this->future = $future;
    }

    /**
     * Retrieve the Future value associated with this Promise
     *
     * @return \Alert\Future
     */
    public function getFuture() {
        return $this->future;
    }

    /**
     * Fufill the Promise's Future value with a successful result
     *
     * @param mixed $value
     * @return void
     */
    public function succeed($value = NULL) {
        if ($value instanceof Future) {
            $value->onComplete(function(Future $f) {
                $this->resolve($f->getError(), $f->getValue());
            });
        } else {
            call_user_func($this->futureResolver, $error = NULL, $value);
        }
    }

    /**
     * Fail the Promise's associated Future
     *
     * @param \Exception $error
     * @return void
     */
    public function fail(\Exception $error) {
        call_user_func($this->futureResolver, $error, $value = NULL);
    }

    /**
     * Resolve the Promise's associated Future
     *
     * @param \Exception $error
     * @param mixed $value
     * @return void
     */
    public function resolve(\Exception $error = NULL, $value = NULL) {
        call_user_func($this->futureResolver, $error, $value);
    }

    /**
     * Resolve the associated Future but only if it has not previously completed
     *
     * @param \Exception $error
     * @param mixed $value
     * @return bool Returns TRUE if the Future was resolved by this operation or FALSE if the
     *              relevant Future was previously resolved
     */
    public function resolveSafely(\Exception $error = NULL, $value = NULL) {
        if ($this->future->isComplete()) {
            $didResolve = FALSE;
        } else {
            $this->resolve($error, $value);
            $didResolve = TRUE;
        }

        return $didResolve;
    }
}
