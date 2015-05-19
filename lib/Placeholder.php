<?php

namespace Amp;

/**
 * A placeholder value that will be resolved at some point in the future by
 * the Promisor that created it.
 */
trait Placeholder {
    private $callbackData = null;
    private $isResolved = false;
    private $watchers = [];
    private $whens = [];
    private $error;
    private $result;

    public function __construct($callbackData = null) {
        $this->callbackData = $callbackData;
    }

    /**
     * Notify the $func callback when the promise resolves (whether successful or not)
     *
     * @param callable $func
     * @return self
     */
    public function when(callable $func) {
        if ($this->isResolved) {
            call_user_func($func, $this->error, $this->result, $this->callbackData);
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

    private function update($progress) {
        if ($this->isResolved) {
            throw new \LogicException(
                "Cannot update resolved promise"
            );
        }

        foreach ($this->watchers as $watcher) {
            call_user_func($watcher, $progress);
        }
    }

    private function resolve(\Exception $error = null, $result = null) {
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
        } else {
            $this->isResolved = true;
            $this->error = $error;
            $this->result = $result;
            foreach ($this->whens as $when) {
                call_user_func($when, $error, $result, $this->callbackData);
            }
            $this->whens = $this->watchers = [];
        }
    }
}
