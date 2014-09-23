<?php

namespace Amp;

/**
 * A Promisor represents a contract to resolve a deferred value at some point in the future
 *
 * A Promisor resolves its associated placeholder value (Promise) Promisor::succeed() or
 * Promisor::fail(). Promisor::update() may be used to notify watchers of progress resolving
 * the future value.
 *
 * Example:
 *
 *     function myAsyncProducer() {
 *         // Create a new promise that needs to be resolved
 *         $future = new Amp\Future;
 *
 *         // When we eventually finish non-blocking value resolution we
 *         // simply call the relevant Promise method to notify any code
 *         // with references to the Promisor's associated Promise:
 *         // $future->succeed($value) -or- $future->fail($exceptionObj)
 *
 *         return $future->promise();
 *     }
 *
 * The following correlations exist between Promisor and Promise methods:
 *
 *  - Promisor::update      |   Promise::watch
 *  - Promisor::succeed     |   Promise::when
 *  - Promisor::fail        |   Promise::when
 */
interface Promisor {
    /**
     * Promise future fulfillment via a temporary placeholder value
     *
     * @return \Amp\Promise
     */
    public function promise();

    /**
     * Update watchers of progress resolving the promised value
     *
     * @param mixed $progress
     * @return void
     */
    public function update($progress);

    /**
     * Resolve the promised value as a success
     *
     * @param mixed $result
     * @return void
     */
    public function succeed($result = null);

    /**
     * Resolve the promised value as a failure
     *
     * @param \Exception $error
     * @return void
     */
    public function fail(\Exception $error);
}
