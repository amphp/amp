<?php

namespace Amp\Internal;

use Amp\Deferred;
use Amp\DisposedException;
use Amp\Loop;
use Amp\Pipeline;
use Amp\Promise;
use React\Promise\PromiseInterface as ReactPromise;

/**
 * Class used internally by {@see Pipeline} implementations. Do not use this class in your code, instead compose your
 * class from one of the available classes implementing {@see Pipeline}.
 *
 * @internal
 *
 * @template TValue
 * @template TSend
 */
final class EmitSource
{
    /** @var Promise|null */
    private $result;

    /** @var bool True if complete() or fail() has been called. */
    private $completed = false;

    /** @var mixed[] */
    private $emittedValues = [];

    /** @var Promise[] */
    private $sendValues = [];

    /** @var Deferred[] */
    private $backPressure = [];

    /** @var Deferred[] */
    private $waiting = [];

    /** @var int */
    private $consumePosition = 0;

    /** @var int */
    private $emitPosition = 0;

    /** @var array|null */
    private $resolutionTrace;

    /** @var bool */
    private $disposed = false;

    /** @var bool */
    private $used = false;

    /** @var callable[]|null */
    private $onDisposal = [];

    /**
     * @return Promise<mixed|null>
     *
     * @psalm-return Promise<TValue|null>
     */
    public function continue(): Promise
    {
        return $this->next(Promise\succeed());
    }

    /**
     * @param mixed $value
     *
     * @psalm-param TSend $value
     *
     * @return Promise<mixed|null>
     *
     * @psalm-return Promise<TValue|null>
     */
    public function send($value): Promise
    {
        if ($this->consumePosition === 0) {
            throw new \Error("Must initialize async generator by calling continue() first");
        }

        return $this->next(Promise\succeed($value));
    }

    /**
     * @param \Throwable $exception
     *
     * @return Promise<mixed|null>
     *
     * @psalm-return Promise<TValue|null>
     */
    public function throw(\Throwable $exception): Promise
    {
        if ($this->consumePosition === 0) {
            throw new \Error("Must initialize async generator by calling continue() first");
        }

        return $this->next(Promise\fail($exception));
    }

    /**
     * @param Promise<mixed> $promise
     *
     * @psalm-param Promise<TSend|null> $promise
     *
     * @return Promise<mixed|null>
     *
     * @psalm-return Promise<TValue|null>
     */
    private function next(Promise $promise): Promise
    {
        $position = $this->consumePosition++;

        if (isset($this->backPressure[$position - 1])) {
            $deferred = $this->backPressure[$position - 1];
            unset($this->backPressure[$position - 1]);
            $deferred->resolve($promise);
        } elseif ($position > 0) {
            // Send-values are indexed as $this->consumePosition - 1.
            $this->sendValues[$position - 1] = $promise;
        }

        if (\array_key_exists($position, $this->emittedValues)) {
            $value = $this->emittedValues[$position];
            unset($this->emittedValues[$position]);

            return Promise\succeed($value);
        }

        if ($this->result) {
            return $this->result;
        }

        $this->waiting[$position] = $deferred = new Deferred;

        return $deferred->promise();
    }

    public function pipe(): Pipeline
    {
        if ($this->used) {
            throw new \Error("A pipeline may be started only once");
        }

        $this->used = true;

        return new AutoDisposingPipeline($this);
    }

    /**
     * @return void
     *
     * @see Pipeline::dispose()
     */
    public function dispose()
    {
        $this->cancel(true);
    }

    public function destroy()
    {
        $this->cancel(false);
    }

    private function cancel(bool $cancelPending)
    {
        try {
            if ($this->result) {
                return; // Pipeline already completed or failed.
            }

            $this->finalize(Promise\fail(new DisposedException), true);
        } finally {
            if ($this->disposed && $cancelPending) {
                $this->triggerDisposal();
            }
        }
    }

    /**
     * @param callable():void $onDispose
     *
     * @return void
     *
     * @see Pipeline::onDisposal()
     */
    public function onDisposal(callable $onDisposal)
    {
        if ($this->disposed) {
            try {
                $onDisposal();
            } catch (\Throwable $e) {
                Loop::defer(static function () use ($e) {
                    throw $e;
                });
            }
            return;
        }

        if ($this->result) {
            return;
        }

        $this->onDisposal[] = $onDisposal;
    }

    /**
     * Emits a value from the pipeline. The returned promise is resolved once the emitted value has been consumed or
     * if the pipeline is completed, failed, or disposed.
     *
     * @param mixed $value
     *
     * @psalm-param TValue $value
     *
     * @return Promise<mixed> Resolves with the sent value once the value has been consumed. Fails with the failure
     *                        reason if the {@see fail()} is called, or with {@see DisposedException} if the pipeline
     *                        is destroyed.
     *
     * @psalm-return Promise<TSend|null>
     *
     * @throws \Error If the pipeline has completed.
     */
    public function emit($value): Promise
    {
        if ($value === null) {
            throw new \TypeError("Pipelines cannot emit NULL");
        }

        if ($value instanceof Promise || $value instanceof ReactPromise) {
            throw new \TypeError("Pipelines cannot emit promises");
        }

        $position = $this->emitPosition++;

        if (isset($this->waiting[$position])) {
            $deferred = $this->waiting[$position];
            unset($this->waiting[$position]);
            $deferred->resolve($value);

            // Send-values are indexed as $this->consumePosition - 1, so use $position for the next value.
            if (isset($this->sendValues[$position])) {
                $promise = $this->sendValues[$position];
                unset($this->sendValues[$position]);
                return $promise;
            }
        } elseif ($this->result) {
            if ($this->completed) {
                throw new \Error("Pipelines cannot emit values after calling complete");
            }

            return $this->result;
        } else {
            $this->emittedValues[$position] = $value;
        }

        if ($this->disposed) {
            if (empty($this->waiting)) {
                $this->triggerDisposal();
            }

            return Promise\succeed();
        }

        $this->backPressure[$position] = $deferred = new Deferred;

        return $deferred->promise();
    }

    /**
     * @return bool True if the pipeline has been completed or failed.
     */
    public function isComplete(): bool
    {
        return $this->completed;
    }

    /**
     * @return bool True if the pipeline was disposed.
     */
    public function isDisposed(): bool
    {
        return $this->disposed && empty($this->waiting);
    }

    /**
     * Completes the pipeline.
     *
     * @return void
     *
     * @throws \Error If the iterator has already been completed.
     */
    public function complete()
    {
        $this->finalize(Promise\succeed());
    }

    /**
     * Fails the pipeline.
     *
     * @param \Throwable $exception
     *
     * @return void
     */
    public function fail(\Throwable $exception)
    {
        if ($exception instanceof DisposedException) {
            throw new \Error("Cannot fail a pipeline with an instance of " . DisposedException::class);
        }

        $this->finalize(Promise\fail($exception));
    }

    /**
     * @param Promise $result
     * @param bool    $disposed Flag if the generator was disposed.
     *
     * @return void
     */
    private function finalize(Promise $result, bool $disposed = false)
    {
        if ($this->completed) {
            $message = "Pipeline has already been completed";

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

        $this->completed = $this->completed ?: !$disposed; // $disposed is false if complete() or fail() invoked
        $this->disposed = $this->disposed ?: $disposed; // Once disposed, do not change flag

        if ($this->completed) { // Record stack trace when calling complete() or fail()
            \assert((function () {
                if (isDebugEnabled()) {
                    $trace = \debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS);
                    \array_shift($trace); // remove current closure
                    $this->resolutionTrace = $trace;
                }

                return true;
            })());
        }

        if ($this->result) {
            return;
        }

        $this->result = $result;

        if ($this->disposed) {
            if (empty($this->waiting)) {
                $this->triggerDisposal();
            }
        } else {
            $this->resolvePending();
        }
    }

    /**
     * Resolves all pending promises returned from {@see continue()} with the result promise.
     */
    private function resolvePending()
    {
        $backPressure = $this->backPressure;
        $this->backPressure = [];

        $waiting = $this->waiting;
        $this->waiting = [];

        foreach ($backPressure as $deferred) {
            $deferred->resolve($this->result);
        }

        foreach ($waiting as $deferred) {
            $deferred->resolve($this->result);
        }
    }

    /**
     * Invokes all pending {@see onDisposal()} callbacks and fails pending {@see continue()} promises.
     */
    private function triggerDisposal()
    {
        \assert($this->disposed, "Pipeline was not disposed on triggering disposal");

        if ($this->onDisposal === null) {
            return;
        }

        $onDisposal = $this->onDisposal;
        $this->onDisposal = null;

        $this->resolvePending();

        /** @psalm-suppress PossiblyNullIterator $alreadyDisposed is a guard against $this->onDisposal being null */
        foreach ($onDisposal as $callback) {
            try {
                $callback();
            } catch (\Throwable $e) {
                Loop::defer(static function () use ($e) {
                    throw $e;
                });
            }
        }
    }
}
