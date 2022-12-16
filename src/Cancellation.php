<?php declare(strict_types=1);

namespace Amp;

/**
 * Cancellations are simple objects that allow registering handlers to subscribe to cancellation requests.
 */
interface Cancellation
{
    /**
     * Subscribes a new handler to be invoked on a cancellation request.
     *
     * This handler might be invoked immediately in case the cancellation has already been requested. Any unhandled
     * exceptions will be thrown into the event loop.
     *
     * @param \Closure(CancelledException) $callback Callback to be invoked on a cancellation request. Will receive a
     * `CancelledException` as first argument that may be used to fail the operation.
     *
     * @return string Identifier that can be used to cancel the subscription.
     */
    public function subscribe(\Closure $callback): string;

    /**
     * Unsubscribes a previously registered handler.
     *
     * The handler will no longer be called as long as this method isn't invoked from a subscribed callback.
     */
    public function unsubscribe(string $id): void;

    /**
     * Returns whether cancellation has been requested yet.
     */
    public function isRequested(): bool;

    /**
     * Throws the `CancelledException` if cancellation has been requested, otherwise does nothing.
     *
     * @throws CancelledException
     */
    public function throwIfRequested(): void;
}
