<?php

namespace Amp\Internal;

use Amp\Deferred;
use Amp\DisposedException;
use Amp\Failure;
use Amp\Loop;
use Amp\Pipeline;
use Amp\Promise;
use Amp\Success;
use function Amp\await;
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

    /** @var [?\Throwable, mixed][] */
    private array $sendValues = [];

    /** @var Deferred[] */
    private array $backPressure = [];

    /** @var Placeholder[] */
    private array $yielding = [];

    /** @var Placeholder[] */
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
        return $this->next(null, null);
    }

    /**
     * @psalm-param TSend $value
     *
     * @psalm-return TValue
     */
    public function send(mixed $value): mixed
    {
        if ($this->consumePosition === 0) {
            throw new \Error("Must initialize async generator by calling continue() first");
        }

        return $this->next(null, $value);
    }

    /**
     * @psalm-return TValue
     */
    public function throw(\Throwable $exception): mixed
    {
        if ($this->consumePosition === 0) {
            throw new \Error("Must initialize async generator by calling continue() first");
        }

        return $this->next($exception, null);
    }

    /**
     * @psalm-param TSend|null $value
     *
     * @psalm-return TValue
     */
    private function next(?\Throwable $exception, mixed $value): mixed
    {
        $position = $this->consumePosition++ - 1;

        // Relieve backpressure from prior emit.
        if (isset($this->yielding[$position])) {
            $placeholder = $this->yielding[$position];
            unset($this->yielding[$position]);
            if ($exception) {
                $placeholder->fail($exception);
            } else {
                $placeholder->resolve($value);
            }
        } elseif (isset($this->backPressure[$position])) {
            $deferred = $this->backPressure[$position];
            unset($this->backPressure[$position]);
            if ($exception) {
                $deferred->fail($exception);
            } else {
                $deferred->resolve($value);
            }
        } elseif ($position >= 0) {
            // Send-values are indexed as $this->consumePosition - 1.
            $this->sendValues[$position] = [$exception, $value];
        }

        ++$position; // Move forward to next emitted value if available.

        if (isset($this->emittedValues[$position])) {
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

        // No value has been emitted, suspend fiber to await next value.
        $this->waiting[$position] = $placeholder = new Placeholder;
        return await(new PrivatePromise($placeholder));
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
     * @param mixed $value
     * @param int $position
     *
     * @psalm-param TValue $value
     *
     * @return array|null Returns [?\Throwable, mixed] or null if no send value is available.
     *
     * @throws \Error If the pipeline has completed.
     */
    private function push(mixed $value, int $position): ?array
    {
        if ($this->completed) {
            throw new \Error("Pipelines cannot emit values after calling complete");
        }

        if ($value === null) {
            throw new \TypeError("Pipelines cannot emit NULL");
        }

        if ($value instanceof Promise) {
            throw new \TypeError("Pipelines cannot emit promises");
        }

        if (isset($this->waiting[$position])) {
            $placeholder = $this->waiting[$position];
            unset($this->waiting[$position]);
            $placeholder->resolve($value);

            if ($this->disposed && empty($this->waiting)) {
                \assert(empty($this->sendValues)); // If $this->waiting is empty, $this->sendValues must be.
                $this->triggerDisposal();
                return [null, null]; // Subsequent push() calls will throw.
            }

            // Send-values are indexed as $this->consumePosition - 1, so use $position for the next value.
            if (isset($this->sendValues[$position])) {
                $pair = $this->sendValues[$position];
                unset($this->sendValues[$position]);
                return $pair;
            }

            return null;
        }

        if ($this->disposed) {
            \assert(isset($this->exception), "Failure exception must be set when disposed");
            // Pipeline has been disposed and no Fibers are still pending.
            return [$this->exception, null];
        }

        $this->emittedValues[$position] = $value;

        return null;
    }

    /**
     * Emits a value from the pipeline. The returned promise is resolved once the emitted value has been consumed or
     * if the pipeline is completed, failed, or disposed.
     *
     * @psalm-param TValue $value
     * @psalm-return Promise<TSend>
     */
    public function emit(mixed $value): Promise
    {
        $position = $this->emitPosition;

        $pair = $this->push($value, $position);

        ++$this->emitPosition;

        if ($pair === null) {
            $this->backPressure[$position] = $deferred = new Deferred;
            return $deferred->promise();
        }

        [$exception, $value] = $pair;

        if ($exception) {
            return new Failure($exception);
        }

        return new Success($value);
    }

    /**
     * Emits a value from the pipeline, suspending execution until the value is consumed.
     *
     * @psalm-param TValue $value
     * @psalm-return TSend
     */
    public function yield(mixed $value): mixed
    {
        $position = $this->emitPosition;

        $pair = $this->push($value, $position);

        ++$this->emitPosition;

        if ($pair === null) {
            $this->yielding[$position] = $placeholder = new Placeholder;
            return await(new PrivatePromise($placeholder));
        }

        [$exception, $value] = $pair;

        if ($exception) {
            throw $exception;
        }

        return $value;
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
            $this->resolvePending();
        }
    }

    /**
     * Resolves all backpressure and outstanding calls for emitted values.
     */
    private function resolvePending(): void
    {
        $backPressure = \array_merge($this->backPressure, $this->yielding);
        $waiting = $this->waiting;

        unset($this->waiting, $this->backPressure, $this->yielding);

        $exception = isset($this->exception) ? $this->exception : null;

        foreach ($backPressure as $deferred) {
            if ($exception) {
                $deferred->fail($exception);
            } else {
                $deferred->resolve();
            }
        }

        foreach ($waiting as $placeholder) {
            if ($exception) {
                $placeholder->fail($this->exception);
            } else {
                $placeholder->resolve();
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

        $this->resolvePending();

        /** @psalm-suppress PossiblyNullIterator $alreadyDisposed is a guard against $this->onDisposal being null */
        foreach ($onDisposal as $callback) {
            defer($callback);
        }
    }
}
