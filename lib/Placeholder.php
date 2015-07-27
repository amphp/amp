<?php

namespace Amp;

/**
 * Standard Promise interface implementation
 *
 * A placeholder value that will be resolved at some point in the future by
 * the Promisor that created it.
 */
trait Placeholder {
    private $isResolved = false;
    private $watchers = [];
    private $whens = [];
    private $error;
    private $result;

    /**
     * Notify the $cb callback when the promise resolves (whether successful or not)
     *
     * @param callable $cb An error-first callback to invoke upon promise resolution
     * @param mixed $cbData Optional data to pass as a third parameter to $cb
     * @return self
     */
    public function when(callable $cb, $cbData = null) {
        if ($this->isResolved) {
            \call_user_func($cb, $this->error, $this->result, $cbData);
        } else {
            $this->whens[] = [$cb, $cbData];
        }

        return $this;
    }

    /**
     * Notify the $cb callback when resolution progress events are emitted
     *
     * @param callable $cb A callback to invoke when data updates are available
     * @param mixed $cbData Optional data to pass as a second parameter to $cb
     * @return self
     */
    public function watch(callable $cb, $cbData = null) {
        if (!$this->isResolved) {
            $this->watchers[] = [$cb, $cbData];
        }

        return $this;
    }

    private function update($progress) {
        if ($this->isResolved) {
            throw new \LogicException(
                "Cannot update resolved promise"
            );
        }
        foreach ($this->watchers as $watcher) {
            list($cb, $cbData) = $watcher;
            \call_user_func($cb, $progress, $cbData);
        }
    }

    /**
     * The error parameter used to fail a promisor must always be an exception
     * instance. However, we cannot typehint this parameter in environments
     * where PHP5.x compatibility is required because PHP7 Throwable
     * instances will break the typehint.
     */
    private function resolve($error = null, $result = null) {
        if ($this->isResolved) {
            throw new \LogicException(
                "Promise already resolved"
            );
        } elseif ($result === $this) {
            throw new \LogicException(
                "A Promise cannot act as its own resolution result"
            );
        } elseif ($result instanceof Promise) {
            $result->when(function($error, $result) {
                $this->resolve($error, $result);
            });
        } elseif (isset($error) && !($error instanceof \Throwable || $error instanceof \Exception)) {
            throw new \InvalidArgumentException(
                "Throwable Exception instance required to fail a promise"
            );
        } else {
            $this->isResolved = true;
            $this->error = $error;
            $this->result = $result;
            foreach ($this->whens as $when) {
                list($cb, $cbData) = $when;
                \call_user_func($cb, $error, $result, $cbData);
            }
            $this->whens = $this->watchers = [];
        }
    }
}
