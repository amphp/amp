<?php

namespace Amp;

/**
 * PipelineSource is a container for a Pipeline that can emit values using the emit() method and completed using the
 * complete() and fail() methods. The contained Pipeline may be accessed using the pipeline() method. This object should
 * not be returned as part of a public API, but used internally to create and emit values to a Pipeline.
 *
 * @template TValue
 */
final class PipelineSource
{
    /** @var Internal\EmitSource<TValue, null> Has public emit, complete, and fail methods. */
    private Internal\EmitSource $source;

    private bool $used = false;

    public function __construct()
    {
        $this->source = new Internal\EmitSource;
    }

    /**
     * Returns a Pipeline that can be given to an API consumer. This method may be called only once!
     *
     * @return Pipeline
     *
     * @psalm-return Pipeline<TValue>
     *
     * @throws \Error If this method is called more than once.
     */
    public function pipe(): Pipeline
    {
        if ($this->used) {
            throw new \Error("A pipeline may be started only once");
        }

        $this->used = true;

        return new Internal\AutoDisposingPipeline($this->source);
    }

    /**
     * Emits a value to the pipeline, returning a promise that is resolved once the emitted value is consumed.
     * Use {@see yield()} to wait until the value is consumed or use {@see await()} on the promise returned
     * to wait at a later time.
     *
     * @param mixed $value
     *
     * @psalm-param TValue $value
     *
     * @return Future<null> Resolves with null when the emitted value has been consumed or fails with
     *                       {@see DisposedException} if the pipeline has been disposed.
     */
    public function emit(mixed $value): Future
    {
        return $this->source->emit($value);
    }

    /**
     * Emits a value to the pipeline and does not return until the emitted value is consumed.
     * Use {@see emit()} to emit a value without waiting for the value to be consumed.
     *
     * @param mixed $value
     *
     * @psalm-param TValue $value
     *
     * @throws DisposedException Thrown if the pipeline is disposed.
     */
    public function yield(mixed $value): void
    {
        $this->source->yield($value);
    }

    /**
     * @return bool True if the pipeline has been completed or failed.
     */
    public function isComplete(): bool
    {
        return $this->source->isComplete();
    }

    /**
     * @return bool True if the pipeline has been disposed.
     */
    public function isDisposed(): bool
    {
        return $this->source->isDisposed();
    }

    /**
     * @param callable():void $onDisposal
     *
     * @return void
     */
    public function onDisposal(callable $onDisposal): void
    {
        $this->source->onDisposal($onDisposal);
    }

    /**
     * Completes the pipeline.
     *
     * @return void
     */
    public function complete(): void
    {
        $this->source->complete();
    }

    /**
     * Fails the pipeline with the given reason.
     *
     * @param \Throwable $reason
     *
     * @return void
     */
    public function error(\Throwable $reason): void
    {
        $this->source->error($reason);
    }
}
