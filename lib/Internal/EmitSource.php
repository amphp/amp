<?php

namespace Amp\Internal;

use Amp\Deferred;
use Amp\DisposedException;
use Amp\Failure;
use Amp\Loop;
use Amp\Pipeline;
use Amp\Promise;
use Amp\Success;
use function Amp\defer;

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
    private bool $completed = false;

    private \Throwable $exception;

    /** @var mixed[] */
    private array $emittedValues = [];

    /** @var Promise[] */
    private array $sendValues = [];

    /** @var Deferred[] */
    private array $backPressure = [];

    /** @var \Continuation[] */
    private array $waiting = [];

    private int $consumePosition = 0;

    private int $emitPosition = 0;

    private ?array $resolutionTrace = null;

    private bool $disposed = false;

    private bool $used = false;

    /** @var callable[]|null */
    private ?array $onDisposal = [];

    /**
     * @psalm-return TValue
     */
    public function continue(): mixed
    {
        return $this->next(new Success);
    }

    /**
     * @psalm-param TSend $value
     *
     * @psalm-return Promise<TValue|null>
     */
    public function send(mixed $value): mixed
    {
        if ($this->consumePosition === 0) {
            throw new \Error("Must initialize async generator by calling continue() first");
        }

        return $this->next(new Success($value));
    }

    /**
     * @psalm-return mixed
     */
    public function throw(\Throwable $exception): mixed
    {
        if ($this->consumePosition === 0) {
            throw new \Error("Must initialize async generator by calling continue() first");
        }

        return $this->next(new Failure($exception));
    }

    /**
     * @psalm-param Promise<TSend|null> $promise
     *
     * @psalm-return TValue
     */
    private function next(Promise $promise): mixed
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
            return $value;
        }

        if ($this->completed || $this->disposed) {
            if (isset($this->exception)) {
                throw $this->exception;
            }
            return null;
        }

        return \Fiber::suspend(
            fn(\Continuation $continuation) => $this->waiting[$position] = $continuation,
            Loop::get()
        );
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
    public function dispose(): void
    {
        $this->cancel(true);
    }

    public function destroy(): void
    {
        $this->cancel(false);
    }

    private function cancel(bool $cancelPending): void
    {
        try {
            if ($this->completed || $this->disposed) {
                return; // Pipeline already completed or failed.
            }

            $this->finalize(new DisposedException, true);
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
    public function onDisposal(callable $onDisposal): void
    {
        if ($this->disposed) {
            defer($onDisposal);
            return;
        }

        if ($this->completed) {
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
    public function emit(mixed $value): Promise
    {
        if ($value === null) {
            throw new \TypeError("Pipelines cannot emit NULL");
        }

        if ($value instanceof Promise) {
            throw new \TypeError("Pipelines cannot emit promises");
        }

        $position = $this->emitPosition++;

        if (isset($this->waiting[$position])) {
            $continuation = $this->waiting[$position];
            unset($this->waiting[$position]);
            Loop::defer(static fn() => $continuation->resume($value));

            if ($this->disposed && empty($this->waiting)) {
                \assert(empty($this->sendValues)); // If $this->waiting is empty, $this->sendValues must be.
                $this->triggerDisposal();
                return new Success; // Subsequent emit() calls will return a Failure.
            }

            // Send-values are indexed as $this->consumePosition - 1, so use $position for the next value.
            if (isset($this->sendValues[$position])) {
                $promise = $this->sendValues[$position];
                unset($this->sendValues[$position]);
                return $promise;
            }
        } elseif ($this->completed) {
            throw new \Error("Pipelines cannot emit values after calling complete");
        } elseif ($this->disposed) {
            \assert(isset($this->exception), "Failure exception must be set when disposed");
            // Pipeline has been disposed and no Continuations are still pending.
            return new Failure($this->exception);
        } else {
            $this->emittedValues[$position] = $value;
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
    public function complete(): void
    {
        $this->finalize();
    }

    /**
     * Fails the pipeline.
     *
     * @param \Throwable $exception
     *
     * @return void
     */
    public function fail(\Throwable $exception): void
    {
        $this->finalize($exception);
    }

    /**
     * @param \Throwable|null $exception Failure reason or null for success.
     * @param bool            $disposed Flag if the generator was disposed.
     *
     * @return void
     */
    private function finalize(?\Throwable $exception = null, bool $disposed = false): void
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

        if (isset($this->exception)) {
            return;
        }

        if ($exception !== null) {
            $this->exception = $exception;
        }

        if ($this->disposed) {
            if (empty($this->waiting)) {
                $this->triggerDisposal();
            }
        } else {
            Loop::defer(fn() => $this->resolvePending());
        }
    }

    /**
     * Resolves all pending promises returned from {@see continue()} with the result promise.
     */
    private function resolvePending(): void
    {
        $backPressure = $this->backPressure;
        $waiting = $this->waiting;

        unset($this->emittedValues, $this->sendValues, $this->waiting, $this->backPressure);

        foreach ($backPressure as $deferred) {
            if (isset($this->exception)) {
                $deferred->fail($this->exception);
            } else {
                $deferred->resolve();
            }
        }

        foreach ($waiting as $continuation) {
            if (isset($this->exception)) {
                $continuation->throw($this->exception);
            } else {
                $continuation->resume();
            }
        }
    }

    /**
     * Invokes all pending {@see onDisposal()} callbacks and fails pending {@see continue()} promises.
     */
    private function triggerDisposal(): void
    {
        \assert($this->disposed, "Pipeline was not disposed on triggering disposal");

        if ($this->onDisposal === null) {
            return;
        }

        $onDisposal = $this->onDisposal;
        $this->onDisposal = null;

        Loop::defer(fn() => $this->resolvePending());

        /** @psalm-suppress PossiblyNullIterator $alreadyDisposed is a guard against $this->onDisposal being null */
        foreach ($onDisposal as $callback) {
            defer($callback);
        }
    }
}
