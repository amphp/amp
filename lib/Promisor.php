<?php

namespace Alert;

/**
 * A Promisor is a contract to resolve a value at some point in the future
 *
 * The Alert\Promisor is NOT the same as the common javascript "promise" idiom. Instead,
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
 *         // Create a new promise that needs to be resolved
 *         $promise = new Alert\Promise;
 *
 *         // When we finish non-blocking value resolution we
 *         // simply call the relevant Promise method depending
 *         // on whether or not retrieval succeeded:
 *         //
 *         // $promise->succeed($value)
 *         // $promise->fail($error)
 *
 *         return $promise->getFuture();
 *     }
 * }
 */
interface Promisor {
    /**
     * Retrieve the Future value associated with this Promise
     *
     * @return \Alert\Future
     */
    public function getFuture();

    /**
     * Fufill the Promise's Future value with a successful result
     *
     * @param mixed $value
     * @return void
     */
    public function succeed($value = NULL);

    /**
     * Fail the Promise's associated Future
     *
     * @param \Exception $error
     * @return void
     */
    public function fail(\Exception $error);

    /**
     * Resolve the Promise's associated Future
     *
     * @param \Exception $error
     * @param mixed $value
     * @return void
     */
    public function resolve(\Exception $error = NULL, $value = NULL);

    /**
     * Resolve the associated Future but only if it has not previously completed
     *
     * @param \Exception $error
     * @param mixed $value
     * @return bool Returns TRUE if the Future was resolved by this operation or FALSE if the
     *              relevant Future was previously resolved
     */
    public function resolveSafely(\Exception $error = NULL, $value = NULL);
}
