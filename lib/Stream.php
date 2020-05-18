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
     * Succeeds with a single element array containing the yielded value if the stream has yielded a value. If the
     * stream completes the promise resolves with null. If the stream fails, the returned promise will fail with the
     * same exception.
     *
     * @return Promise<array> Resolves with null if the stream has completed.
     *
     * @psalm-return Promise<list<TValue>>
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
