<?php

namespace Amp;

/**
 * A PrivatePromisor creates read-only Promise instances that can only be
 * resolved by holders of the PrivatePromisor instance. This creates an
 * additional layer of API protection beyond the PublicPromisor.
 */
trait PrivatePromisor {
    private $resolver;
    private $updater;
    private $promise;

    public function __construct() {
        // @TODO Replace PrivatePlaceholder with an anonymous class once PHP7 is required
        $placeholder = new PrivatePlaceholder;
        $resolver = function($error = null, $result = null) {
            // bound to private $placeholder::resolve()
            $this->resolve($error, $result);
        };
        $updater = function($progress) {
            // bound to private $placeholder::update()
            \call_user_func([$this, "update"], $progress);
        };
        $this->resolver = $resolver->bindTo($placeholder, $placeholder);
        $this->updater = $updater->bindTo($placeholder, $placeholder);
        $this->promise = $placeholder;
    }

    /**
     * Promise future fulfillment of the returned placeholder value
     *
     * @return \Amp\Promise
     */
    public function promise() {
        return $this->promise;
    }

    /**
     * Update watchers of progress resolving the promised value
     *
     * @param mixed $progress1, $progress2, ... $progressN
     * @return void
     */
    public function update($progress) {
        \call_user_func($this->updater, $progress);
    }

    /**
     * Resolve the associated promise placeholder as a success
     *
     * @param mixed $result
     * @return void
     */
    public function succeed($result = null) {
        \call_user_func($this->resolver, $error = null, $result);
    }

    /**
     * Resolve the associated promise placeholder as a failure
     *
     * The error parameter used to fail a promisor must always be an exception
     * instance. However, we cannot typehint this parameter in environments
     * where PHP5.x compatibility is required because PHP7 Throwable
     * instances will break the typehint.
     *
     * @param mixed $error An Exception or Throwable in PHP7 environments
     * @return void
     */
    public function fail($error) {
        \call_user_func($this->resolver, $error, $result = null);
    }
}
