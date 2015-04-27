<?php

namespace Amp;

/**
 * A PrivateFuture creates a read-only Promise that may *only* be fulfilled by holders of the
 * actual PrivateFuture instance. This provides an additional layer of API protection over
 * the standard Future Promisor implementation whose Promise can be resolved by any code
 * holding a reference to the Future instance.
 */
class PrivateFuture implements Promisor {
    private $promise;
    private $resolver;

    public function __construct() {
        $this->promise = new Unresolved;
        $this->resolver = function(bool $isUpdate, ...$args) {
            if ($isUpdate) {
                // bound to private Unresolved::update() at call-time
                $this->update(...$args);
            } else {
                // bound to private Unresolved::resolve() at call-time
                $this->resolve(...$args);
            }
        };
    }

    /**
     * Promise future fulfillment via a temporary placeholder value
     * 
     * @return \Amp\Promise
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
    public function update(...$progress) {
        $this->resolver->call($this->promise, $isUpdate = true, ...$progress);
    }

    /**
     * Resolve the promised value as a success
     *
     * @param mixed $result
     * @return void
     */
    public function succeed($result = null) {
        $this->resolver->call($this->promise, $isUpdate = false, $error = null, $result);
    }

    /**
     * Resolve the promised value as a failure
     *
     * @param \Exception $error
     * @return void
     */
    public function fail(\Exception $error) {
        $this->resolver->call($this->promise, $isUpdate = false, $error, $result = null);
    }
}
