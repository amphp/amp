<?php declare(strict_types=1);

namespace Amp;

/**
 * Cancellations are simple objects that allow registering handlers to subscribe to cancellation requests.
 *
 * What {@see subscribe()} and {@see unsubscribe()} may do depends on the implementation. Callers that accept any
 * cancellation inherit that implementation's capabilities.
 *
 * @psalm-purity-template read-props <= C(read-props|write-this-props|write-props|write-refs|io) <= read-props|write-this-props|write-props|write-refs|io
 *
 * @api
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
     *
     * @psalm-pure
     * @psalm-purity-from-template C
     */
    public function subscribe(\Closure $callback): string;

    /**
     * Unsubscribes a previously registered handler.
     *
     * The handler will no longer be called as long as this method isn't invoked from a subscribed callback.
     *
     * @psalm-pure
     * @psalm-purity-from-template C
     */
    public function unsubscribe(string $id): void;

    /**
     * Returns whether cancellation has been requested yet.
     *
     * @psalm-capabilities read-props
     */
    public function isRequested(): bool;

    /**
     * Throws the `CancelledException` if cancellation has been requested, otherwise does nothing.
     *
     * @throws CancelledException
     *
     * @psalm-capabilities read-props|write-this-props|write-props|write-refs
     */
    public function throwIfRequested(): void;
}
