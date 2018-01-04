<?php

namespace Amp\Internal;

use Amp\Deferred;
use Amp\DisposedException;
use Amp\Failure;
use Amp\Flow;
use Amp\Promise;
use Amp\Success;

/**
 * Trait used by Iterator implementations. Do not use this trait in your code, instead compose your class from one of
 * the available classes implementing \Amp\Iterator.
 *
 * @internal
 */
trait Generator
{
    /** @var \Amp\Promise|null */
    private $complete;

    /** @var mixed[] */
    private $values = [];

    /** @var \Amp\Deferred[] */
    private $backPressure = [];

    /** @var \Amp\Deferred|null */
    private $waiting;

    /** @var bool */
    private $disposed = false;

    /** @var null|array */
    private $resolutionTrace;

    /** @var int. */
    private $nextKey = 0;

    /**
     * Returns an flow instance that when destroyed fails further calls to yield() with an instance of \Amp\DisposedException.
     *
     * @return \Amp\Flow
     */
    public function iterate(): Flow
    {
        $values = &$this->values;
        $backPressure = &$this->backPressure;
        $complete = &$this->complete;
        $waiting = &$this->waiting;
        $disposed = &$this->disposed;

        return new class($values, $backPressure, $disposed, $waiting, $complete) implements Flow {
            /** @var \Amp\Promise|null */
            private $complete;

            /** @var mixed[] */
            private $values = [];

            /** @var \Amp\Deferred[] */
            private $backPressure = [];

            /** @var \Amp\Deferred|null */
            private $waiting;

            /** @var bool */
            private $disposed = false;

            /** @var int */
            private $position = -1;

            public function __construct(
                array &$values,
                array &$backpressure,
                bool &$disposed,
                Promise &$waiting = null,
                Promise &$complete = null
            ) {
                $this->values = &$values;
                $this->backPressure = &$backpressure;
                $this->disposed = &$disposed;
                $this->waiting = &$waiting;
                $this->complete = &$complete;
            }

            public function __destruct()
            {
                if (!empty($this->backPressure)) {
                    for ($key = \key($this->backPressure); isset($this->backPressure[$key]); $key++) {
                        $deferred = $this->backPressure[$key];
                        unset($this->values[$key], $this->backPressure[$key]);
                        $deferred->resolve();
                    }
                }

                $this->disposed = true;
            }

            public function continue(): Promise
            {
                if ($this->waiting !== null) {
                    throw new \Error("The prior promise returned must resolve before invoking this method again");
                }

                if (isset($this->backPressure[$this->position])) {
                    $deferred = $this->backPressure[$this->position];
                    unset($this->backPressure[$this->position]);
                    $deferred->resolve();
                }

                unset($this->values[$this->position]);

                ++$this->position;

                if (isset($this->values[$this->position])) {
                    return new Success($this->values[$this->position]);
                }

                if ($this->complete) {
                    return $this->complete;
                }

                $this->waiting = new Deferred;
                return $this->waiting->promise();
            }
        };
    }

    /**
     * Yields a value from the flow. The returned promise is resolved with the yielded value once all disposed
     * have been invoked.
     *
     * @param mixed $value
     * @param mixed $key Using null auto-generates an incremental integer key.
     *
     * @return \Amp\Promise
     *
     * @throws \Error If the iterator has completed.
     */
    private function yield($value, $key = null): Promise
    {
        if ($this->complete) {
            throw new \Error("Flows cannot yield values after calling complete");
        }

        if ($this->disposed) {
            return new Failure(new DisposedException);
        }

        if ($key === null) {
            $key = $this->nextKey++;
        } elseif (\is_int($key) && $key > $this->nextKey) {
            $this->nextKey = $key + 1;
        }

        $this->values[] = $yielded = [$value, $key];
        $this->backPressure[] = $pressure = new Deferred;

        if ($this->waiting !== null) {
            $waiting = $this->waiting;
            $this->waiting = null;
            $waiting->resolve($yielded);
        }

        return $pressure->promise();
    }

    /**
     * Completes the flow.
     *
     * @throws \Error If the flow has already been completed.
     */
    private function complete()
    {
        if ($this->complete) {
            $message = "Flow has already been completed";

            if (isset($this->resolutionTrace)) {
                $trace = formatStacktrace($this->resolutionTrace);
                $message .= ". Previous completion trace:\n\n{$trace}\n\n";
            } else {
                // @codeCoverageIgnoreStart
                $message .= ", define environment variable AMP_DEBUG or const AMP_DEBUG = true and enable assertions "
                    . "for a stacktrace of the previous resolution.";
                // @codeCoverageIgnoreEnd
            }

            throw new \Error($message);
        }

        \assert((function () {
            $env = \getenv("AMP_DEBUG");
            if (($env !== "0" && $env !== "false") || (\defined("AMP_DEBUG") && \AMP_DEBUG)) {
                $trace = \debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS);
                \array_shift($trace); // remove current closure
                $this->resolutionTrace = $trace;
            }

            return true;
        })());

        $this->complete = new Success;

        if ($this->waiting !== null) {
            $waiting = $this->waiting;
            $this->waiting = null;
            $waiting->resolve($this->complete);
        }
    }

    private function fail(\Throwable $exception)
    {
        $this->complete = new Failure($exception);

        if ($this->waiting !== null) {
            $waiting = $this->waiting;
            $this->waiting = null;
            $waiting->resolve($this->complete);
        }
    }
}
