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
    public function promise(): Promise {
        return $this;
    }

    /**
     * Update watchers of progress resolving the promised value
     *
     * @param mixed $progress1, $progress2, ... $progressN
     * @return void
     */
    public function update(...$progress) {
        if ($this->isResolved) {
            throw new \LogicException(
                'Cannot update resolved promise'
            );
        }

        $baseArgs = $progress;
        foreach ($this->watchers as $watcher) {
            $args = $baseArgs;
            $args[] = $watcher[1];
            ($watcher[0])(...$args);
        }
    }

    /**
     * Resolve the promised value as a success
     *
     * @param mixed $result
     * @return void
     */
    public function succeed($result = null) {
        $this->resolve($error = null, $result);
    }

    /**
     * Resolve the promised value as a failure
     *
     * @param \BaseException $error
     * @return void
     */
    public function fail(\BaseException $error) {
        $this->resolve($error, $result = null);
    }
}
