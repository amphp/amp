<?php

namespace Amp;

/**
 * A placeholder value that will be resolved at some point in the future by
 * the Promisor that created it.
 */
class Unresolved implements Promise {
    private $isResolved = false;
    private $watchers = [];
    private $whens = [];
    private $error;
    private $result;

    /**
     * Notify the $func callback when the promise resolves (whether successful or not)
     */
    public function when(callable $func): Unresolved {
        if ($this->isResolved) {
            $func($this->error, $this->result);
        } else {
            $this->whens[] = $func;
        }

        return $this;
    }

    /**
     * Notify the $func callback when resolution progress events are emitted
     */
    public function watch(callable $func): Unresolved {
        if (!$this->isResolved) {
            $this->watchers[] = $func;
        }

        return $this;
    }

    protected function resolve(\Exception $error = null, $result = null) {
        if ($this->isResolved) {
            throw new \LogicException(
                'Promise already resolved'
            );
        } elseif ($result === $this) {
            throw new \LogicException(
                'A Promise cannot act as its own resolution result'
            );
        } elseif ($result instanceof Promise) {
            $result->when(function($error, $result) {
                $this->resolve($error, $result);
            });
        } else {
            $this->isResolved = true;
            $this->error = $error;
            $this->result = $result;
            foreach ($this->whens as $when) {
                $when($error, $result);
            }
            $this->whens = $this->watchers = [];
        }
    }

    protected function update($progress) {
        if ($this->isResolved) {
            throw new \LogicException(
                'Cannot update resolved promise'
            );
        }

        foreach ($this->watchers as $watcher) {
            $watcher($progress);
        }
    }
}
