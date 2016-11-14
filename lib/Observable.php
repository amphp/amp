<?php declare(strict_types = 1);

namespace Amp;

use Interop\Async\Promise;

/**
 * Represents a set of asynchronous values. An observable is analogous to an asynchronous generator, yielding (emitting)
 * values when they are available, returning a value (success value) when the observable completes or throwing an
 * exception (failure reason).
 */
interface Observable extends Promise {
    /**
     * Registers a callback to be invoked each time value is emitted from the observable. If the function returns an
     * promise, back-pressure is applied to the promise until the returned promise is resolved.
     *
     * Exceptions thrown from $onNext (or failures of promises returned from $onNext) will fail the returned
     * Subscriber with the thrown exception.
     *
     * @param callable $onNext Function invoked each time a value is emitted from the observable.
     */
    public function subscribe(callable $onNext);
}
