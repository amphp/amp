<?php

namespace Amp;

/**
 * Defines a flow, an asynchronous generator that yields key/value pairs as data is available.
 */
interface Flow
{
    /**
     * Succeeds with a [value, key] pair or null if no more values are available. If the flow fails, the returned promise
     * will fail with the same exception.
     *
     * @return \Amp\Promise<[mixed $value, mixed $key]|null>
     *
     * @throws \Error If the prior promise returned from this method has not resolved.
     * @throws \Throwable The exception used to fail the flow.
     */
    public function continue(): Promise;
}
