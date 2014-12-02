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
        $unresolved = new Unresolved;
        $resolver = function(\Exception $error = null, $result = null) {
            $this->resolve($error, $result); // bound to private Unresolved::resolve()
        };
        $updater = function($progress) {
            $this->update($progress); // bound to private Unresolved::update()
        };
        $this->resolver = $resolver->bindTo($unresolved, $unresolved);
        $this->updater = $updater->bindTo($unresolved, $unresolved);
        $this->promise = $unresolved;
    }

    /**
     * Promise future fulfillment via a temporary placeholder value
     *
     * @return \Amp\Promise
     */
    public function promise() {
        return $this->promise;
    }

    /**
     * Update watchers of progress resolving the promised value
     *
     * @param mixed $progress
     * @return void
     */
    public function update($progress) {
        $updater = $this->updater;
        $updater($progress);
    }

    /**
     * Resolve the promised value as a success
     *
     * @param mixed $result
     * @return void
     */
    public function succeed($result = null) {
        $resolver = $this->resolver;
        $resolver($error = null, $result);
    }

    /**
     * Resolve the promised value as a failure
     *
     * @param \Exception $error
     * @return void
     */
    public function fail(\Exception $error) {
        $resolver = $this->resolver;
        $resolver($error, $result = null);
    }
}
