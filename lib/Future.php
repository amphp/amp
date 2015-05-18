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
    public function promise(): Promise {
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function when(callable $func) {
        if ($this->isResolved) {
            $func($this->error, $this->result);
        } else {
            $this->whens[] = $func;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function watch(callable $func) {
        if (!$this->isResolved) {
            $this->watchers[] = $func;
        }
    }

    /**
     * {@inheritDoc}
     * @throws \LogicException if the promise has already resolved
     */
    public function update($data) {
        if ($this->isResolved) {
            throw new \LogicException(
                'Cannot update resolved promise'
            );
        }

        foreach ($this->watchers as $watcher) {
            $watcher($data);
        }
    }

    /**
     * {@inheritDoc}
     * @throws \LogicException if the promise has already resolved or the result is the current instance
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
     * {@inheritDoc}
     * @throws \LogicException if the promise has already resolved
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
