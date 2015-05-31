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
     * Notify the $func callback when the promise resolves (whether successful or not)
     *
     * @param callable $func An error-first callback to invoke upon promise resolution
     * @param mixed $data Optional data to pass as a third parameter to $func
     * @return void
     */
    public function when(callable $func, $data = null) {
        if ($this->isResolved) {
            call_user_func($func, $this->error, $this->result, $data);
        } else {
            $this->whens[] = [$func, $data];
        }
    }

    /**
     * Notify the $func callback when resolution progress events are emitted
     *
     * @param callable $func A callback to invoke when data updates are available
     * @param mixed $data Optional data to pass as a second parameter to $func
     * @return void
     */
    public function watch(callable $func, $data = null) {
        if (!$this->isResolved) {
            $this->watchers[] = [$func, $data];
        }
    }

    private function update($progress) {
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
                call_user_func($when[0], $error, $result, $when[1]);
            }
            $this->whens = $this->watchers = [];
        }
    }
}
