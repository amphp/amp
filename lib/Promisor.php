<?php

namespace Amp;

/**
 * A Promisor represents a contract to resolve a deferred value at some point in the future
 *
 * A Promisor resolves its associated placeholder value (Promise) Promisor::succeed() or
 * Promisor::fail(). Promisor::update() may be used to notify watchers of progress resolving
 * the deferred value.
 *
 * Example:
 *
 *     function myAsyncProducer() {
 *         // Create a new promisor that needs to be resolved
 *         $promisor = new Amp\Deferred;
 *
 *         // When we eventually finish non-blocking value resolution we
 *         // simply call the relevant Promise method to notify any code
 *         // with references to the Promisor's associated Promise:
 *         // $promisor->succeed($value) -or- $promisor->fail($exceptionObj)
 *
 *         return $promisor->promise();
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
     * Promise deferred fulfillment via a temporary placeholder value
     *
     * @return \Amp\Promise
     */
    public function promise();

    /**
     * Update watchers of progress resolving the promised value
     *
     * Implementations must support variadic argument passing to update
     * even though 5.5-compatibility prevents us from specifying it as
     * part of the API.
     *
     * @param mixed $progress1, $progress2, ... $progressN
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
     * The error parameter used to fail a promisor must always be an exception
     * instance. However, we cannot typehint this parameter in environments
     * where PHP5.x compatibility is required because PHP7 Throwable
     * instances will break the typehint.
     *
     * @param mixed $error An Exception or Throwable in PHP7 environments
     * @return void
     */
    public function fail($error);
}
