<?php

namespace Amp\Internal;

use Amp\Deferred;
use Amp\DisposedException;
use Amp\Loop;
use Amp\Promise;
use Amp\Stream;
use React\Promise\PromiseInterface as ReactPromise;

/**
 * Class used internally by {@see Stream} implementations. Do not use this class in your code, instead compose your
 * class from one of the available classes implementing {@see Stream}.
 *
 * @internal
 *
 * @template TValue
 * @template TSend
 */
final class EmitSource
{
    /** @var \Throwable|null */
    private $exception;

    /** @var bool */
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
    private $onCompletion = [];

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

        if ($this->exception) {
            return Promise\fail($this->exception);
        }

        if ($this->completed) {
            return Promise\succeed();
        }

        $this->waiting[$position] = $deferred = new Deferred;

        return $deferred->promise();
    }

    public function stream(): Stream
    {
        if ($this->used) {
            throw new \Error("A stream may be started only once");
        }

        $this->used = true;

        return new AutoDisposingStream($this);
    }

    /**
     * @return void
     *
     * @see Stream::dispose()
     */
    public function dispose()
    {
        if ($this->completed || $this->disposed) {
            return; // Stream already completed or failed.
        }

        $this->finalize(new DisposedException, true);
    }

    /**
     * @param callable():void $onDispose
     *
     * @return void
     *
     * @see Stream::onDisposal()
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

        if ($this->completed) {
            return;
        }

        $this->onDisposal[] = $onDisposal;
    }

    /**
     * @param callable(?\Throwable):void $onDispose
     *
     * @return void
     *
     * @see Stream::onCompletion()
     */
    public function onCompletion(callable $onCompletion)
    {
        if ($this->completed) {
            try {
                $onCompletion($this->exception);
            } catch (\Throwable $e) {
                Loop::defer(static function () use ($e) {
                    throw $e;
                });
            }
            return;
        }

        if ($this->disposed) {
            return;
        }

        $this->onCompletion[] = $onCompletion;
    }

    /**
     * Emits a value from the stream. The returned promise is resolved once the emitted value has been consumed or
     * if the stream is completed, failed, or disposed.
     *
     * @param mixed $value
     *
     * @psalm-param TValue $value
     *
     * @return Promise<mixed> Resolves with the sent value once the value has been consumed. Fails with the failure
     *                        reason if the {@see fail()} is called, or with {@see DisposedException} if the stream
     *                        is destroyed.
     *
     * @psalm-return Promise<TSend|null>
     *
     * @throws \Error If the stream has completed.
     */
    public function emit($value): Promise
    {
        if ($this->completed || $this->exception) {
            if ($this->disposed) {
                \assert($this->exception instanceof DisposedException);
                return Promise\fail($this->exception);
            }

            throw new \Error("Streams cannot emit values after calling complete");
        }

        if ($value === null) {
            throw new \TypeError("Streams cannot emit NULL");
        }

        if ($value instanceof Promise || $value instanceof ReactPromise) {
            throw new \TypeError("Streams cannot emit promises");
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
        } else {
            $this->emittedValues[$position] = $value;
        }

        $this->backPressure[$position] = $deferred = new Deferred;

        return $deferred->promise();
    }

    /**
     * @return bool True if the stream has been completed or failed.
     */
    public function isComplete(): bool
    {
        return $this->completed;
    }

    /**
     * @return bool True if the stream was disposed.
     */
    public function isDisposed(): bool
    {
        return $this->disposed;
    }

    /**
     * Completes the stream.
     *
     * @return void
     *
     * @throws \Error If the iterator has already been completed.
     */
    public function complete()
    {
        $this->finalize();
    }

    /**
     * Fails the stream.
     *
     * @param \Throwable $exception
     *
     * @return void
     */
    public function fail(\Throwable $exception)
    {
        if ($exception instanceof DisposedException) {
            throw new \Error("Cannot fail a stream with an instance of " . DisposedException::class);
        }

        $this->finalize($exception);
    }

    /**
     * @param \Throwable $exception
     * @param bool       $disposed Flag if the generator was disposed.
     *
     * @return void
     */
    private function finalize(\Throwable $exception = null, bool $disposed = false)
    {
        if ($this->completed) {
            $message = "Stream has already been completed";

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

        $alreadyDisposed = $this->disposed;

        $this->completed = !$disposed; // $disposed is false if complete() or fail() invoked
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

        if ($alreadyDisposed) {
            return;
        }

        $this->exception = $exception;

        $waiting = $this->waiting;
        $this->waiting = [];

        $result = $exception ? Promise\fail($exception) : Promise\succeed();

        foreach ($waiting as $deferred) {
            $deferred->resolve($result);
        }

        $onCompletion = $this->onCompletion;
        $this->onCompletion = null;

        if (!$disposed) {
            /** @psalm-suppress PossiblyNullIterator $alreadyDisposed is a guard against $this->onCompletion being null */
            foreach ($onCompletion as $callback) {
                try {
                    $callback($exception);
                } catch (\Throwable $e) {
                    Loop::defer(static function () use ($e) {
                        throw $e;
                    });
                }
            }
        }

        $onDisposal = $this->onDisposal;
        $this->onDisposal = null;

        if ($disposed) {
            $backPressure = $this->backPressure;
            $this->backPressure = [];

            foreach ($backPressure as $deferred) {
                $deferred->resolve($result);
            }

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
}