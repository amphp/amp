<?php

namespace Amp;

class Future implements Promisor, Promise {
    private $reactor;
    private $isWaiting = false;
    private $isResolved = false;
    private $watchers = [];
    private $whens = [];
    private $error;
    private $result;

    /**
     * @param \Amp\Reactor $reactor
     */
    public function __construct(Reactor $reactor) {
        $this->reactor = $reactor;
    }

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
     * Block script execution indefinitely until the promise resolves
     *
     * @throws \Exception
     * @return mixed
     */
    public function wait() {
        if ($this->error) {
            throw $this->error;
        } elseif ($this->isResolved) {
            return $this->result;
        }

        $resolvedError;
        $resolvedResult;

        $this->whens[] = function($error, $result) use (&$resolvedError, &$resolvedResult) {
            $resolvedError = $error;
            $resolvedResult = $result;
            $this->isWaiting = false;
        };

        $this->isWaiting = true;
        while ($this->isWaiting) {
            $this->reactor->tick();
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
