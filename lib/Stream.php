<?php

namespace Amp;

/**
 * Represents a set of asynchronous values. A stream is analogous to an asynchronous generator, yielding (emitting)
 * values when they are available, returning a value (success value) when the stream completes or throwing an
 * exception (failure reason).
 */
interface Stream extends Promise {
    /**
     * Registers a callback to be invoked each time value is emitted from the stream. If the function returns an
     * promise, back-pressure is applied to the promise until the returned promise is resolved.
     *
     * Exceptions thrown from $onNext (or failures of promises returned from $onNext) will fail the returned
     * Subscriber with the thrown exception.
     *
     * @param callable $onNext Function invoked each time a value is emitted from the stream.
     */
    public function listen(callable $onNext);
}
