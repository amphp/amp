<?php

namespace Amp;

/**
 * A stream is an asynchronous set of ordered values.
 *
 * @template-covariant TValue
 */
interface Stream
{
    /**
     * Succeeds with the emitted value if the stream has emitted a value or null if the stream has completed.
     * If the stream fails, the returned promise will fail with the same exception.
     *
     * @return Promise<mixed|null> Resolves with null if the stream has completed.
     *
     * @psalm-return Promise<TValue|null>
     *
     * @throws \Throwable The exception used to fail the stream.
     */
    public function continue(): Promise;

    /**
     * Disposes of the stream, indicating the consumer is no longer interested in the stream output.
     *
     * @return void
     */
    public function dispose();
}
