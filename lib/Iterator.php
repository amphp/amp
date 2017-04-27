<?php

namespace Amp;

/**
 * Defines an asynchronous iterator over a set of values that is designed to be used within a coroutine.
 */
interface Iterator {
    /**
     * Succeeds with true if an emitted value is available by calling getCurrent() or false if the stream has resolved.
     * If the stream fails, the returned promise will fail with the same exception.
     *
     * @return \Amp\Promise<bool>
     *
     * @throws \Error If the prior promise returned from this method has not resolved.
     * @throws \Throwable The exception used to fail the stream.
     */
    public function advance(): Promise;

    /**
     * Gets the last emitted value or throws an exception if the stream has completed.
     *
     * @return mixed Value emitted from the stream.
     *
     * @throws \Error If the stream has resolved or advance() was not called before calling this method.
     * @throws \Throwable The exception used to fail the stream.
     */
    public function getCurrent();
}
