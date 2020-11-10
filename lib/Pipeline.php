<?php

namespace Amp;

/**
 * A pipeline is an asynchronous set of ordered values.
 *
 * @template-covariant TValue
 */
interface Pipeline extends \Traversable
{
    /**
     * Returns the emitted value if the pipeline has emitted a value or null if the pipeline has completed.
     * If the pipeline fails, the exception will be thrown from this method.
     *
     * This method exists primarily for async consumption using Amp\async(fn() => $pipeline->continue()).
     *
     * @return mixed Returns null if the pipeline has completed.
     *
     * @psalm-return TValue
     *
     * @throws \Throwable The exception used to fail the pipeline.
     */
    public function continue(): mixed;

    /**
     * Disposes of the pipeline, indicating the consumer is no longer interested in the pipeline output.
     *
     * @return void
     */
    public function dispose(): void;
}
