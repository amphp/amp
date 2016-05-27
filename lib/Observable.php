<?php

namespace Amp;

interface Observable {
    /**
     * Registers a callback to be invoked each time value is emitted from the observable. If the function returns an
     * awaitable, backpressure is applied to the awaitable until the returned awaitable is resolved.
     *
     * Exceptions thrown from $onNext (or failures of awaitables returned from $onNext) will fail the returned
     * Disposable with the thrown exception.
     *
     * @param callable $onNext Function invoked each time a value is emitted from the observable.
     *
     * @return \Amp\Disposable
     */
    public function subscribe(callable $onNext);
}
