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
        $this->promise = new PrivatePlaceholder;
        $this->resolver = function(bool $isUpdate, ...$args) {
            if ($isUpdate) {
                // bound to private PrivatePlaceholder::update() at call-time
                $this->update(...$args);
            } else {
                // bound to private PrivatePlaceholder::resolve() at call-time
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
     * @param mixed $progress1, $progress2, ... $progressN
     * @return void
     */
    public function update(...$data) {
        $this->resolver->call($this->promise, $isUpdate = true, ...$data);
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
     * @param \BaseException $error
     * @return void
     */
    public function fail(\BaseException $error) {
        $this->resolver->call($this->promise, $isUpdate = false, $error, $result = null);
    }
}
