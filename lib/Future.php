<?php

namespace Amp;

class Future implements Promisor, Promise {
    private $isResolved = false;
    private $watchers = [];
    private $whens = [];
    private $error;
    private $result;

    /**
     * Retrieve the Promise placeholder for this deferred value
     *
     * This implementation acts as both Promisor and Promise so we simply return the
     * current instance. If users require a Promisor that can only be resolved by code
     * holding a reference to the Promisor they may instead use Amp\PrivateFuture.
     *
     * @return \Amp\Promise
     */
    public function promise() {
        return $this;
    }

    /**
     * Notify the $func callback when the promise resolves (whether successful or not)
     *
     * $func callbacks are invoked with parameters in error-first style.
     *
     * @param callable $func
     * @return self
     */
    public function when(callable $func) {
        if ($this->isResolved) {
            $func($this->error, $this->result);
        } else {
            $this->whens[] = $func;
        }

        return $this;
    }

    /**
     * Notify the $func callback when resolution progress events are emitted
     *
     * @param callable $func
     * @return self
     */
    public function watch(callable $func) {
        if (!$this->isResolved) {
            $this->watchers[] = $func;
        }

        return $this;
    }

    /**
     * This method is deprecated. New code should use Amp\wait($promise) instead.
     */
    public function wait() {
        trigger_error(
            'Amp\\Promise::wait() is deprecated and scheduled for removal. ' .
            'Please update code to use Amp\\wait($promise) instead.',
            E_USER_DEPRECATED
        );

        $isWaiting = true;
        $resolvedError = $resolvedResult = null;
        $this->when(function($error, $result) use (&$isWaiting, &$resolvedError, &$resolvedResult) {
            $isWaiting = false;
            $resolvedError = $error;
            $resolvedResult = $result;
        });
        $reactor = getReactor();
        while ($isWaiting) {
            $reactor->tick();
        }
        if ($resolvedError) {
            throw $resolvedError;
        }

        return $resolvedResult;
    }

    /**
     * Update watchers of resolution progress events
     *
     * @param mixed $progress
     * @throws \LogicException
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
     * @throws \LogicException
     * @return void
     */
    public function succeed($result = null) {
        if ($this->isResolved) {
            throw new \LogicException(
                'Promise already resolved'
            );
        } elseif ($result === $this) {
            throw new \LogicException(
                'A Promise cannot act as its own resolution result'
            );
        } elseif ($result instanceof Promise) {
            $result->when(function(\Exception $error = null, $result = null) {
                if ($error) {
                    $this->fail($error);
                } else {
                    $this->succeed($result);
                }
            });
        } else {
            $this->isResolved = true;
            $this->result = $result;
            $error = null;
            foreach ($this->whens as $when) {
                $when($error, $result);
            }
            $this->whens = $this->watchers = [];
        }
    }

    /**
     * Resolve the promised value as a failure
     *
     * @param \Exception $error
     * @throws \LogicException If the Promise has already resolved
     * @return void
     */
    public function fail(\Exception $error) {
        if ($this->isResolved) {
            throw new \LogicException(
                'Promise already resolved'
            );
        }

        $this->isResolved = true;
        $this->error = $error;
        $result = null;

        foreach ($this->whens as $when) {
            $when($error, $result);
        }
        $this->whens = $this->watchers = [];
    }
}
