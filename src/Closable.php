<?php declare(strict_types=1);

namespace Amp;

/**
 * A resource that can be closed.
 *
 * This package does not implement the interface. Closing a resource may suspend the event loop and mutate state, so
 * the methods are impure and implementors are not restricted further.
 *
 * @psalm-mutable
 *
 * @api
 */
interface Closable
{
    /**
     * Closes the resource, marking it as unusable.
     * Whether pending operations are aborted or not is implementation dependent.
     *
     * @psalm-impure
     */
    public function close(): void;

    /**
     * Returns whether this resource has been closed.
     *
     * @return bool `true` if closed, otherwise `false`.
     *
     * @psalm-impure
     */
    public function isClosed(): bool;

    /**
     * Registers a callback that is invoked when this resource is closed.
     *
     * @param \Closure():void $onClose
     *
     * @psalm-impure
     */
    public function onClose(\Closure $onClose): void;
}
