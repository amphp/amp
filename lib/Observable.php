<?php declare(strict_types = 1);

namespace Amp;

use Interop\Async\Awaitable;

/**
 * Represents a set of asynchronous values. An observable is analogous to an asynchronous generator, yielding (emitting)
 * values when they are available, returning a value (success value) when the observable completes or throwing an
 * exception (failure reason).
 */
interface Observable extends Awaitable {
    /**
     * Registers a callback to be invoked each time value is emitted from the observable. If the function returns an
     * awaitable, back-pressure is applied to the awaitable until the returned awaitable is resolved.
     *
     * Exceptions thrown from $onNext (or failures of awaitables returned from $onNext) will fail the returned
     * Subscriber with the thrown exception.
     *
     * @param callable $onNext Function invoked each time a value is emitted from the observable.
     */
    public function subscribe(callable $onNext);
}
