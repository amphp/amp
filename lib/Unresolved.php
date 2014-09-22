<?php

namespace Alert;

/**
 * A placeholder value that will be resolved at some point in the future by
 * the Promisor that created it.
 */
class Unresolved implements Promise {
    private $reactor;
    private $isWaiting = false;
    private $isResolved = false;
    private $watchers = [];
    private $whens = [];
    private $error;
    private $result;

    /**
     * @param \Alert\Reactor $reactor
     */
    public function __construct(Reactor $reactor = null) {
        $this->reactor = $reactor ?: ReactorFactory::select();
    }

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
     * Block script execution indefinitely until the promise resolves
     *
     * @throws \Exception
     * @return mixed
     */
    public function wait() {
        if ($this->error) {
            throw $error;
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
