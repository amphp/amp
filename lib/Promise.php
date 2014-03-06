<?php

namespace Alert;

/**
 * A Promise is a writable container used to complete a future once its value is resolved.
 *
 * The Alert\Promise is NOT the same as the common javascript "promise" idiom. Instead,
 * Alert defines a "Promise" as an internal agreement made by producers of asynchronous
 * results to fulfill a placeholder (Future) value at some point in the future. In this
 * regard an Alert\Promise has more in common with the Scala promise API than javascript
 * implementations.
 *
 * A Promise resolves its associated Future placeholder with a value using the succeed()
 * method. Conversely, a promise can also report future failure by passing an Exception
 * via the failure() method.
 *
 * Example:
 *
 * class MyAsyncProducer {
 *     public function retrieveValueAsynchronously() {
 *         // Create a new promise that needs to be fulfilled
 *         $promise = new Alert\Promise;
 *
 *         $future = $promise->getFuture();
 *
 *         // When we finish non-blocking value resolution we
 *         // simply call the relevant Promise method depending
 *         // on whether or not retrieval succeeded:
 *         //
 *         // $promise->succeed($value)
 *         // $promise->fail($error)
 *
 *         return $future;
 *     }
 * }
 */
class Promise {
    private $value;
    private $error;
    private $future;
    private $isResolved = FALSE;
    private $futureResolver;

    public function __construct() {
        $futureResolver = function(\Exception $error = NULL, $value = NULL) {
            $this->resolve($error, $value);
        };
        $future = new Future;
        $this->futureResolver = $futureResolver->bindTo($future, $future);
        $this->future = $future;
    }

    /**
     * Retrieve the Future value associated with this Promise
     *
     * @return \Amp\Future
     */
    public function getFuture() {
        return $this->future;
    }

    /**
     * Resolve the Promise's associated Future placeholder
     *
     * @param \Exception $error
     * @param mixed $value
     * @return void
     */
    public function resolve(\Exception $error = NULL, $value = NULL) {
        $this->isResolved = TRUE;
        $futureResolver = $this->futureResolver;
        $futureResolver($error, $value);
    }

    /**
     * Resolve the associated Future but only if it has not previously been resolved
     *
     * @param \Exception $error
     * @param mixed $value
     * @return bool Returns TRUE if the Future was resolved by this operation or FALSE if the
     *              relevant Future was previously resolved
     */
    public function resolveSafely(\Exception $error = NULL, $value = NULL) {
        if ($this->future->isPending()) {
            $this->resolve($error, $value);
            $fulfilled = TRUE;
        } else {
            $fulfilled = FALSE;
        }

        return $fulfilled;
    }

    /**
     * Fail the Promise's associated Future
     *
     * @param \Exception $error
     * @return void
     */
    public function fail(\Exception $error) {
        $this->isResolved = TRUE;
        $futureResolver = $this->futureResolver;
        $futureResolver($error, $value = NULL);
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
            $this->isResolved = TRUE;
            $futureResolver = $this->futureResolver;
            $futureResolver($error = NULL, $value);
        }
    }
}
