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

    private function resolve(\Exception $error = null, $result = null) {
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

    private function update($progress) {
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
