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
        return $this->source->pipe();
    }

    /**
     * Emits a value to the pipeline.
     *
     * @param mixed $value
     *
     * @psalm-param TValue $value
     *
     * @return Promise<null> Resolves with null when the emitted value has been consumed or fails with
     *                       {@see DisposedException} if the pipeline has been destroyed.
     */
    public function emit($value): Promise
    {
        return $this->source->emit($value);
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
    public function fail(\Throwable $reason): void
    {
        $this->source->fail($reason);
    }
}
