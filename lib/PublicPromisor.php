<?php

namespace Amp;

trait PublicPromisor {
    use Placeholder;

    /**
     * Promise future fulfillment via a temporary placeholder value
     *
     * This implementation acts as both Promisor and Promise so we simply return the
     * current instance.
     *
     * @return \Amp\Promise
     */
    public function promise() {
        return $this;
    }

    /**
     * Update watchers of progress resolving the promised value
     *
     * @param mixed $progress
     * @return void
     */
    public function update($progress) {
        if ($this->isResolved) {
            throw new \LogicException(
                'Cannot update resolved promise'
            );
        }

        foreach ($this->watchers as $watcher) {
            $watcher($progress);
        }
    }

    /**
     * Resolve the promised value as a success
     *
     * @param mixed $result
     * @return void
     */
    public function succeed($result = null) {
        return $this->resolve($error = null, $result);
    }

    /**
     * Resolve the promised value as a failure
     *
     * @return void
     */
    public function fail(\Exception $error) {
        return $this->resolve($error, $result = null);
    }
}
