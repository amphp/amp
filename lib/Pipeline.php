<?php

namespace Amp;

/**
 * A pipeline is an asynchronous set of ordered values.
 *
 * @template-covariant TValue
 */
interface Pipeline
{
    /**
     * Succeeds with the emitted value if the pipeline has emitted a value or null if the pipeline has completed.
     * If the pipeline fails, the returned promise will fail with the same exception.
     *
     * @return Promise<mixed|null> Resolves with null if the pipeline has completed.
     *
     * @psalm-return Promise<TValue|null>
     *
     * @throws \Throwable The exception used to fail the pipeline.
     */
    public function continue(): Promise;

    /**
     * Disposes of the pipeline, indicating the consumer is no longer interested in the pipeline output.
     *
     * @return void
     */
    public function dispose();
}
