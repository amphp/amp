<?php

namespace Amp;

/**
 * A PrivatePromisor creates read-only Promise instances that can only be
 * resolved by holders of the PrivatePromisor instance. This creates an
 * additional layer of API protection beyond the PublicPromisor.
 */
trait PrivatePromisor {
    private $promise;
    private $resolver;

    public function __construct() {
        $this->promise = new Unresolved;
        $this->resolver = function(bool $isUpdate, ...$args) {
            if ($isUpdate) {
                // bound to private Unresolved::update() at call-time
                $this->update($args[0]);
            } else {
                // bound to private Unresolved::resolve() at call-time
                $this->resolve($args[0], $args[1]);
            }
        };
    }

    /**
     * Promise future fulfillment of the returned placeholder value
     *
     * @return \Amp\Promise
     */
    public function promise(): Promise {
        return $this->promise;
    }

    /**
     * Update subscribers of progress resolving the promised value
     *
     * @param mixed $data
     * @return void
     */
    public function update($data) {
        $this->resolver->call($this->promise, $isUpdate = true, $data);
    }

    /**
     * Resolve the associated promise placeholder as a success
     *
     * @param mixed $result
     * @return void
     */
    public function succeed($result = null) {
        $this->resolver->call($this->promise, $isUpdate = false, $error = null, $result);
    }

    /**
     * Resolve the associated promise placeholder as a failure
     *
     * @param \Exception $error
     * @return void
     */
    public function fail(\Exception $error) {
        $this->resolver->call($this->promise, $isUpdate = false, $error, $result = null);
    }
}
