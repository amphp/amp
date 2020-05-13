<?php

namespace Amp\Internal;

use Amp\Deferred;
use Amp\DisposedException;
use Amp\Failure;
use Amp\TransformationStream;
use Amp\Promise;
use Amp\Stream;
use Amp\Success;
use React\Promise\PromiseInterface as ReactPromise;

/**
 * Trait used by {@see Stream} implementations. Do not use this trait in your code, instead compose your class from one
 * of the available classes implementing {@see Stream}.
 *
 * @internal
 *
 * @template TValue
 * @template TSend
 * @template TReturn
 */
trait Yielder
{
    /** @var Promise|null */
    private $result;

    /** @var bool */
    private $completed = false;

    /** @var mixed[] */
    private $yieldedValues = [];

    /** @var Promise[] */
    private $sendValues = [];

    /** @var Deferred[] */
    private $backPressure = [];

    /** @var Deferred[] */
    private $waiting = [];

    /** @var int */
    private $nextKey = 0;

    /** @var int */
    private $consumePosition = 0;

    /** @var int */
    private $yieldPosition = 0;

    /** @var array|null */
    private $resolutionTrace;

    /** @var bool */
    private $disposed = false;

    /** @var bool */
    private $used = false;

    /**
     * @return Promise<array>
     */
    public function continue(): Promise
    {
        return $this->next(new Success);
    }

    /**
     * @param mixed $value
     *
     * @psalm-param TSend $value
     *
     * @return Promise<array>
     */
    public function send($value): Promise
    {
        if ($this->consumePosition === 0) {
            throw new \Error("Must initialize async generator by calling continue() first");
        }

        return $this->next(new Success($value));
    }

    /**
     * @param \Throwable $exception
     *
     * @return Promise<array>
     */
    public function throw(\Throwable $exception): Promise
    {
        if ($this->consumePosition === 0) {
            throw new \Error("Must initialize async generator by calling continue() first");
        }

        return $this->next(new Failure($exception));
    }

    /**
     * @param Promise<TSend|null> $promise
     *
     * @return Promise<array>
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

        if (isset($this->yieldedValues[$position])) {
            $tuple = $this->yieldedValues[$position];
            unset($this->yieldedValues[$position]);

            return new Success($tuple);
        }

        if ($this->result) {
            return $this->result;
        }

        $this->waiting[$position] = $deferred = new Deferred;

        return $deferred->promise();
    }

    public function transform(): TransformationStream
    {
        return new TransformationStream($this);
    }

    private function createStream(): Stream
    {
        \assert($this instanceof Stream, \sprintf("Users of this trait must implement %s to call %s", Stream::class, __METHOD__));

        if ($this->used) {
            throw new \Error("A stream may be started only once");
        }

        $this->used = true;

        /**
         * @psalm-suppress InvalidArgument
         * @psalm-suppress InternalClass
         */
        return new AutoDisposingStream($this);
    }

    private function createGenerator(): GeneratorStream
    {
        \assert($this instanceof GeneratorStream, \sprintf("Users of this trait must implement %s to call %s", GeneratorStream::class, __METHOD__));

        if ($this->used) {
            throw new \Error("A stream may be started only once");
        }

        $this->used = true;

        /**
         * @psalm-suppress InvalidArgument
         * @psalm-suppress InternalClass
         */
        return new AutoDisposingGenerator($this);
    }

    public function dispose()
    {
        if ($this->result) {
            return; // Stream already completed or failed.
        }

        $this->finalize(new Failure(new DisposedException), true);
    }

    /**
     * Yields a value from the stream. The returned promise is resolved once the yielded value has been consumed or
     * if the stream is completed, failed, or disposed.
     *
     * @param mixed $value
     *
     * @psalm-param TValue $value
     *
     * @return Promise<TSend> Resolves with the key of the yielded value once the value has been consumed. Fails with
     *                        the failure reason if the {@see fail()} is called, or with {@see DisposedException} if the
     *                        stream is destroyed.
     *
     * @psalm-return Promise<TSend>
     *
     * @throws \Error If the stream has completed.
     */
    public function yield($value): Promise
    {
        if ($this->result) {
            if ($this->disposed) {
                return $this->result; // Promise failed with an instance of DisposedException.
            }

            throw new \Error("Streams cannot yield values after calling complete");
        }

        if ($value instanceof Promise || $value instanceof ReactPromise) {
            throw new \TypeError("Streams cannot yield promises");
        }

        $key = $this->nextKey++;
        $tuple = [$value, $key];
        $position = $this->yieldPosition++;

        if (isset($this->waiting[$position])) {
            $deferred = $this->waiting[$position];
            unset($this->waiting[$position]);
            $deferred->resolve($tuple);

            // Send-values are indexed as $this->consumePosition - 1, so use $position for the next value.
            if (isset($this->sendValues[$position])) {
                $promise = $this->sendValues[$position];
                unset($this->sendValues[$position]);
                return $promise;
            }
        } else {
            $this->yieldedValues[$position] = $tuple;
        }

        $this->backPressure[$position] = $deferred = new Deferred;

        return $deferred->promise();
    }

    /**
     * Completes the stream.
     **
     * @return void
     *
     * @throws \Error If the iterator has already been completed.
     */
    public function complete()
    {
        $this->finalize(new Success);
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
        $this->finalize(new Failure($exception));
    }

    /**
     * @param Promise $result Promise with the generator result, either a null success or a failed promise.
     * @param bool    $disposed Flag if the generator was disposed.
     *
     * @return void
     */
    private function finalize(Promise $result, bool $disposed = false)
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

        $this->completed = !$disposed;
        $this->disposed = $disposed;

        if ($this->result) {
            return;
        }

        \assert((function () {
            $env = \getenv("AMP_DEBUG") ?: "0";
            if (($env !== "0" && $env !== "false") || (\defined("AMP_DEBUG") && \AMP_DEBUG)) {
                $trace = \debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS);
                \array_shift($trace); // remove current closure
                $this->resolutionTrace = $trace;
            }

            return true;
        })());

        $this->result = $result;

        $waiting = $this->waiting;
        $this->waiting = [];

        foreach ($waiting as $deferred) {
            $deferred->resolve($result);
        }

        if ($disposed) {
            $backPressure = $this->backPressure;
            $this->backPressure = [];

            foreach ($backPressure as $deferred) {
                $deferred->resolve($result);
            }
        }
    }
}
