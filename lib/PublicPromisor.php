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
     * @param mixed $progress1, $progress2, ... $progressN
     * @return void
     */
    public function update($progress) {
        if ($this->isResolved) {
            throw new \LogicException(
                "Cannot update resolved promise"
            );
        }

        $baseArgs = func_get_args();
        foreach ($this->watchers as $watcher) {
            $args = $baseArgs;
            $args[] = $watcher[1];
            \call_user_func_array($watcher[0], $args);
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
     * The error parameter used to fail a promisor must always be an exception
     * instance. However, we cannot typehint this parameter in environments
     * where PHP5.x compatibility is required because PHP7 Throwable
     * instances will break the typehint.
     *
     * @param mixed $error An Exception or Throwable in PHP7 environments
     * @return void
     */
    public function fail($error) {
        $this->resolve($error, $result = null);
    }
}
