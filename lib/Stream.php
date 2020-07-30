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

    /**
     * Registers a callback to be invoked *only* if the stream is disposed before being completed or failed.
     *
     * @param callable():void $onDisposal
     *
     * @return void
     */
    public function onDisposal(callable $onDisposal);

    /**
     * Registers a callback to be invoked when the stream is completed or failed. If the stream is failed, the exception
     * used to fail the stream is given as the first argument to the callback. Null is given as the first argument if
     * the stream is completed.
     *
     * @param callable(?\Throwable):void $onCompletion
     *
     * @return void
     */
    public function onCompletion(callable $onCompletion);
}
