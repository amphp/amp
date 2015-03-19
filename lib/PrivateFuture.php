<?php

namespace Amp;

/**
 * A PrivateFuture creates a read-only Promise that may *only* be fulfilled by holders of the
 * actual PrivateFuture instance. This provides an additional layer of API protection over
 * the standard Future Promisor implementation whose Promise can be resolved by any code
 * holding a reference to the Future instance.
 */
class PrivateFuture implements Promisor {
    private $resolver;
    private $updater;
    private $promise;

    public function __construct() {
        $this->promise = new Unresolved;
        $this->resolver = function(\Exception $error = null, $result = null) {
            $this->resolve($error, $result); // bound to private Unresolved::resolve() at call-time
        };
        $this->updater = function($progress) {
            $this->update($progress); // bound to private Unresolved::update() at call-time
        };
    }

    /**
     * Promise future fulfillment via a temporary placeholder value
     */
    public function promise(): Promise {
        return $this->promise;
    }

    /**
     * Update subscribers of progress resolving the promised value
     *
     * @param mixed $progress
     * @return void
     */
    public function update($progress) {
        $this->updater->call($this->promise, $progress);
    }

    /**
     * Resolve the promised value as a success
     *
     * @param mixed $result
     * @return void
     */
    public function succeed($result = null) {
        $this->resolver->call($this->promise, $error = null, $result);
    }

    /**
     * Resolve the promised value as a failure
     *
     * @param \Exception $error
     * @return void
     */
    public function fail(\Exception $error) {
        $this->resolver->call($this->promise, $error, $result = null);
    }
}
