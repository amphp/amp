<?php

namespace Amp\Internal;

use Amp\CallableMaker;
use Amp\Deferred;
use Amp\DisposedException;
use Amp\Failure;
use Amp\Iterator;
use Amp\Promise;
use Amp\Success;
use React\Promise\PromiseInterface as ReactPromise;

/**
 * Trait used by Iterator implementations. Do not use this trait in your code, instead compose your class from one of
 * the available classes implementing \Amp\Iterator.
 *
 * @internal
 */
trait Producer {
    use CallableMaker;

    /** @var \Amp\Promise|null */
    private $complete;

    /** @var mixed[] */
    private $values = [];

    /** @var \Amp\Deferred[] */
    private $backPressure = [];

    /** @var \Amp\Deferred|null */
    private $waiting;

    /** @var int */
    private $position = -1;

    /** @var bool */
    private $disposed = false;

    /** @var \Amp\DisposedException|null */
    private $exception;

    /** @var null|array */
    private $resolutionTrace;

    /**
     * Returns an iterator instance that when destroyed fails the producer with an instance of \Amp\DisposedException.
     *
     * @return \Amp\Iterator
     */
    public function iterate(): Iterator {
        $advance = $this->callableFromInstanceMethod("advance");
        $current = $this->callableFromInstanceMethod("getCurrent");
        $dispose = $this->callableFromInstanceMethod("dispose");

        return new class($advance, $current, $dispose) implements Iterator {
            /** @var callable */
            private $advance;

            /** @var callable */
            private $current;

            /** @var callable */
            private $dispose;

            public function __construct(callable $advance, callable $current, callable $dispose) {
                $this->advance = $advance;
                $this->current = $current;
                $this->dispose = $dispose;
            }

            public function __destruct() {
                ($this->dispose)();
            }

            public function advance(): Promise {
                return ($this->advance)();
            }

            public function getCurrent() {
                return ($this->current)();
            }
        };
    }

    /**
     * Emits a value from the iterator. The returned promise is resolved with the emitted value once it has been
     * consumed.
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

        if ($this->disposed) {
            if (!$this->exception) {
                $this->exception = new DisposedException("The iterator has been disposed");
            }

            return new Failure($this->exception);
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

    private function advance(): Promise {
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

        if (\array_key_exists($this->position, $this->values)) {
            return new Success(true);
        }

        if ($this->complete) {
            return $this->complete;
        }

        $this->waiting = new Deferred;
        return $this->waiting->promise();
    }

    private function getCurrent() {
        if (empty($this->values) && $this->complete) {
            throw new \Error("The iterator has completed");
        }

        if (!\array_key_exists($this->position, $this->values)) {
            throw new \Error("Promise returned from advance() must resolve before calling this method");
        }

        return $this->values[$this->position];
    }

    private function dispose() {
        if ($this->backPressure) {
            $this->exception = new DisposedException("The iterator has been disposed");
            for ($key = \key($this->backPressure); isset($this->backPressure[$key]); $key++) {
                $deferred = $this->backPressure[$key];
                unset($this->values[$key], $this->backPressure[$key]);
                $deferred->fail($this->exception);
            }
        }

        $this->disposed = true;
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
