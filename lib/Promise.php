<?php

namespace Amp;

/**
 * Representation of the future value of an asynchronous operation.
 *
 * @template-covariant TValue
 * @psalm-yield TValue
 */
interface Promise
{
    /**
     * Registers a callback to be invoked when the promise is resolved. Note that using this method directly is
     * generally not recommended. Use the {@see await()} function to await promise resolution or use one of the
     * combinator functions in the Amp\Promise namespace, such as {@see \Amp\Promise\all()}.
     *
     * If this method is called multiple times, additional handlers will be registered instead of replacing any already
     * existing handlers.
     *
     * Registered callbacks MUST be invoked asynchronously when the promise is resolved using a defer watcher in the
     * event-loop.
     *
     * Exceptions MUST NOT be thrown from this method. Any exceptions thrown from invoked callbacks MUST be
     * forwarded to the event-loop error handler by re-throwing from a defer watcher.
     *
     * Note: You shouldn't implement this interface yourself. Instead, provide a method that returns a promise for the
     * operation you're implementing. Objects other than pure placeholders implementing it are a very bad idea.
     *
     * @param callable $onResolved The first argument shall be `null` on success, while the second shall be `null` on
     *     failure.
     *
     * @psalm-param callable(\Throwable|null, mixed):Promise|null $onResolved
     *
     * @return void
     */
    public function onResolve(callable $onResolved): void;
}
