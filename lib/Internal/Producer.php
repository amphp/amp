<?php

namespace Amp\Internal;

use Amp\Deferred;
use Amp\Failure;
use Amp\Promise;
use Amp\Success;
use React\Promise\PromiseInterface as ReactPromise;

/**
 * Trait used by Iterator implementations. Do not use this trait in your code, instead compose your class from one of
 * the available classes implementing \Amp\Iterator.
 * Note that it is the responsibility of the user of this trait to ensure that listeners have a chance to listen first
 * before emitting values.
 *
 * @internal
 */
trait Producer {
    /** @var \Amp\Promise|null */
    private $complete;

    /** @var mixed[] */
    private $values = [];

    /** @var \Amp\Deferred[] */
    private $backPressure = [];

    /** @var int */
    private $position = -1;

    /** @var \Amp\Deferred|null */
    private $waiting;

    /** @var null|array */
    private $resolutionTrace;

    /**
     * {@inheritdoc}
     */
    public function advance(): Promise {
        if ($this->waiting !== null) {
            throw new \Error("The prior promise returned must resolve before invoking this method again");
        }

        if (isset($this->backPressure[$this->position])) {
            $future = $this->backPressure[$this->position];
            unset($this->values[$this->position], $this->backPressure[$this->position]);
            $future->resolve();
        }

        ++$this->position;

        if (\array_key_exists($this->position, $this->values)) {
            return new Success(true);
        }

        if ($this->complete) {
            return $this->complete;
        }

        $this->waiting = new Deferred;
        return $this->waiting->promise();
    }

    /**
     * {@inheritdoc}
     */
    public function getCurrent() {
        if (empty($this->values) && $this->complete) {
            throw new \Error("The iterator has completed");
        }

        if (!\array_key_exists($this->position, $this->values)) {
            throw new \Error("Promise returned from advance() must resolve before calling this method");
        }

        return $this->values[$this->position];
    }

    /**
     * Emits a value from the iterator. The returned promise is resolved with the emitted value once all listeners
     * have been invoked.
     *
     * @param mixed $value
     *
     * @return \Amp\Promise
     *
     * @throws \Error If the iterator has completed.
     */
    private function emit($value): Promise {
        if ($this->complete) {
            throw new \Error("Iterators cannot emit values after calling complete");
        }

        if ($value instanceof ReactPromise) {
            $value = Promise\adapt($value);
        }

        if ($value instanceof Promise) {
            $deferred = new Deferred;
            $value->onResolve(function ($e, $v) use ($deferred) {
                if ($this->complete) {
                    $deferred->fail(
                        new \Error("The iterator was completed before the promise result could be emitted")
                    );
                    return;
                }

                if ($e) {
                    $this->fail($e);
                    $deferred->fail($e);
                    return;
                }

                $deferred->resolve($this->emit($v));
            });

            return $deferred->promise();
        }

        $this->values[] = $value;
        $this->backPressure[] = $pressure = new Deferred;

        if ($this->waiting !== null) {
            $waiting = $this->waiting;
            $this->waiting = null;
            $waiting->resolve(true);
        }

        return $pressure->promise();
    }

    /**
     * Completes the iterator.
     *
     * @throws \Error If the iterator has already been completed.
     */
    private function complete() {
        if ($this->complete) {
            $message = "Iterator has already been completed";

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

        $this->complete = new Success(false);

        if ($this->waiting !== null) {
            $waiting = $this->waiting;
            $this->waiting = null;
            $waiting->resolve($this->complete);
        }
    }

    private function fail(\Throwable $exception) {
        $this->complete = new Failure($exception);

        if ($this->waiting !== null) {
            $waiting = $this->waiting;
            $this->waiting = null;
            $waiting->resolve($this->complete);
        }
    }
}
